<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/google_drive.php';
require_admin();
set_time_limit(0);

$eventId = filter_input(INPUT_GET, 'event', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
$stmt = db()->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event) {
    http_response_code(404);
    exit('ไม่พบกิจกรรม');
}

$message = '';
$error = '';
$details = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $files = drive_list_images($event['drive_folder_id']);
        $dir = STORAGE_PATH . '/photos/' . $event['id'];
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('สร้างโฟลเดอร์จัดเก็บรูปไม่สำเร็จ');
        }
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $synced = 0;
        $indexed = 0;

        foreach ($files as $file) {
            $mime = (string) ($file['mimeType'] ?? '');
            if (!isset($extensions[$mime])) {
                continue;
            }
            $relative = 'storage/photos/' . $event['id'] . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $file['id']) . '.' . $extensions[$mime];
            $absolute = dirname(__DIR__) . '/' . $relative;
            $modified = date('Y-m-d H:i:s', strtotime((string) ($file['modifiedTime'] ?? 'now')));

            $existing = db()->prepare('SELECT id, drive_modified_at, local_path FROM photos WHERE event_id = ? AND drive_file_id = ? LIMIT 1');
            $existing->execute([$event['id'], $file['id']]);
            $old = $existing->fetch();
            $needsDownload = !$old || !is_file($absolute) || (string) $old['drive_modified_at'] !== $modified;
            if ($needsDownload) {
                drive_download((string) $file['id'], $absolute);
            }

            $upsert = db()->prepare(
                'INSERT INTO photos (event_id,drive_file_id,file_name,mime_type,local_path,file_size,drive_modified_at,is_visible,face_indexed,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,1,0,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE file_name=VALUES(file_name),mime_type=VALUES(mime_type),local_path=VALUES(local_path),file_size=VALUES(file_size),drive_modified_at=VALUES(drive_modified_at),updated_at=NOW()'
            );
            $upsert->execute([
                $event['id'], $file['id'], $file['name'], $mime, $relative,
                (int) ($file['size'] ?? (is_file($absolute) ? filesize($absolute) : 0)), $modified,
            ]);
            $photoId = $old ? (int) $old['id'] : (int) db()->lastInsertId();
            if ($photoId === 0) {
                $lookup = db()->prepare('SELECT id FROM photos WHERE event_id = ? AND drive_file_id = ?');
                $lookup->execute([$event['id'], $file['id']]);
                $photoId = (int) $lookup->fetchColumn();
            }
            $synced++;

            if ($needsDownload || !$old) {
                $payload = json_encode(['event_id' => (int) $event['id'], 'photo_id' => $photoId, 'path' => $absolute], JSON_UNESCAPED_SLASHES);
                $ch = curl_init(rtrim(FACE_SERVICE_URL, '/') . '/index');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => 300,
                ]);
                $body = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);
                if ($body !== false && $status < 400) {
                    db()->prepare('UPDATE photos SET face_indexed = 1 WHERE id = ?')->execute([$photoId]);
                    $indexed++;
                } else {
                    $details[] = 'ทำดัชนีไม่สำเร็จ: ' . $file['name'];
                }
            }
        }
        $message = "ซิงก์ {$synced} รูป และทำดัชนีใหม่ {$indexed} รูปเรียบร้อย";
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

page_header('ซิงก์รูป', true);
require __DIR__ . '/_nav.php';
?>
<h1>ซิงก์รูป: <?= e($event['title']) ?></h1>
<?php if ($message): ?><div class="notice success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<?php foreach ($details as $detail): ?><div class="notice error"><?= e($detail) ?></div><?php endforeach; ?>
<div class="form-card">
    <p><strong>Folder ID:</strong> <?= e($event['drive_folder_id']) ?></p>
    <p>ระบบจะดาวน์โหลดรูปที่เพิ่มหรือแก้ไขลงแคช และส่งแต่ละรูปไปตรวจจับใบหน้าทั้งหมดในภาพ</p>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
        <button class="btn" type="submit">เริ่มซิงก์และทำดัชนีใบหน้า</button>
    </form>
</div>
<?php page_footer(); ?>
