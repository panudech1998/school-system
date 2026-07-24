<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/face_service.php';
require_login();
set_time_limit(0);

$eventId = filter_input(INPUT_GET, 'event', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

$statement = db()->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
$statement->execute([$eventId]);
$event = $statement->fetch();

if (!$event) {
    http_response_code(404);
    exit('ไม่พบกิจกรรม');
}

$error = '';

function local_image_files(string $folder): array
{
    $files = [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        if (!in_array(strtolower($file->getExtension()), $allowedExtensions, true)) {
            continue;
        }
        $files[] = $file->getPathname();
    }

    natcasesort($files);
    return array_values($files);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $sourceFolder = (string) $event['drive_folder_id'];
        if (!is_dir($sourceFolder)) {
            throw new RuntimeException('ไม่พบโฟลเดอร์รูป กรุณาเปิด Google Drive for desktop และตรวจตำแหน่งโฟลเดอร์');
        }
        if (!is_readable($sourceFolder)) {
            throw new RuntimeException('Apache ไม่มีสิทธิ์อ่านโฟลเดอร์รูป');
        }

        $sourceFiles = local_image_files($sourceFolder);
        if (!$sourceFiles) {
            throw new RuntimeException('ไม่พบรูป JPG, PNG หรือ WEBP ในโฟลเดอร์นี้');
        }

        $destinationDirectory = STORAGE_PATH . '/photos/' . $event['id'];
        if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0775, true) && !is_dir($destinationDirectory)) {
            throw new RuntimeException('สร้างโฟลเดอร์เก็บรูปไม่สำเร็จ');
        }

        $mimeExtensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $synced = 0;
        $indexed = 0;
        $unchanged = 0;
        $failed = [];
        $seenSourceIds = [];

        foreach ($sourceFiles as $sourcePath) {
            $realSource = realpath($sourcePath) ?: $sourcePath;
            $mime = (string) $finfo->file($realSource);
            if (!isset($mimeExtensions[$mime])) {
                continue;
            }

            $normalizedPath = strtolower(str_replace('\\', '/', $realSource));
            $sourceId = hash('sha256', $normalizedPath);
            $seenSourceIds[] = $sourceId;
            $extension = $mimeExtensions[$mime];
            $relativePath = 'storage/photos/' . $event['id'] . '/' . $sourceId . '.' . $extension;
            $absolutePath = dirname(__DIR__) . '/' . $relativePath;
            $modifiedTimestamp = filemtime($realSource) ?: time();
            $modifiedAt = date('Y-m-d H:i:s', $modifiedTimestamp);
            $fileSize = filesize($realSource) ?: 0;

            $existingStatement = db()->prepare('SELECT id,drive_modified_at,file_size,face_indexed FROM photos WHERE event_id=? AND drive_file_id=? LIMIT 1');
            $existingStatement->execute([$event['id'], $sourceId]);
            $existing = $existingStatement->fetch();

            $needsCopy = !$existing
                || !is_file($absolutePath)
                || (string) $existing['drive_modified_at'] !== $modifiedAt
                || (int) $existing['file_size'] !== $fileSize;

            if ($needsCopy) {
                if (!copy($realSource, $absolutePath)) {
                    $failed[] = 'คัดลอกรูปไม่สำเร็จ: ' . basename($realSource);
                    continue;
                }
            }

            $upsert = db()->prepare(
                'INSERT INTO photos(event_id,drive_file_id,file_name,mime_type,local_path,file_size,drive_modified_at,is_visible,face_indexed,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,?,1,0,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE file_name=VALUES(file_name),mime_type=VALUES(mime_type),local_path=VALUES(local_path),file_size=VALUES(file_size),drive_modified_at=VALUES(drive_modified_at),is_visible=1,updated_at=NOW()'
            );
            $upsert->execute([
                $event['id'],
                $sourceId,
                basename($realSource),
                $mime,
                $relativePath,
                $fileSize,
                $modifiedAt,
            ]);

            $photoId = $existing ? (int) $existing['id'] : (int) db()->lastInsertId();
            if (!$photoId) {
                $idStatement = db()->prepare('SELECT id FROM photos WHERE event_id=? AND drive_file_id=?');
                $idStatement->execute([$event['id'], $sourceId]);
                $photoId = (int) $idStatement->fetchColumn();
            }

            if ($needsCopy || !$existing || !(int) ($existing['face_indexed'] ?? 0)) {
                try {
                    $result = face_index_photo((int) $event['id'], $photoId, $absolutePath);
                    $faces = (int) ($result['faces'] ?? 0);
                    db()->prepare('UPDATE photos SET face_indexed=? WHERE id=?')->execute([$faces > 0 ? 1 : 0, $photoId]);
                    if ($faces > 0) {
                        $indexed++;
                    } else {
                        $failed[] = 'ไม่พบใบหน้า: ' . basename($realSource);
                    }
                } catch (Throwable $exception) {
                    $failed[] = 'ทำดัชนีไม่สำเร็จ: ' . basename($realSource) . ' — ' . $exception->getMessage();
                }
            } else {
                $unchanged++;
            }

            $synced++;
        }

        if ($seenSourceIds) {
            $placeholders = implode(',', array_fill(0, count($seenSourceIds), '?'));
            $parameters = array_merge([(int) $event['id']], $seenSourceIds);
            db()->prepare("UPDATE photos SET is_visible=0 WHERE event_id=? AND drive_file_id NOT IN ({$placeholders})")->execute($parameters);
        }

        $_SESSION['sync_failures'] = $failed;
        audit('sync_local_drive_folder', (string) $event['id']);
        flash('success', "ตรวจพบ {$synced} รูป ทำดัชนี {$indexed} รูป และไม่มีการเปลี่ยนแปลง {$unchanged} รูป");
        redirect('admin/sync.php?event=' . $event['id']);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$failures = $_SESSION['sync_failures'] ?? [];
unset($_SESSION['sync_failures']);
$folderReady = is_dir((string) $event['drive_folder_id']) && is_readable((string) $event['drive_folder_id']);

page_header('ซิงก์รูป', true);
require __DIR__ . '/_nav.php';
?>
<h1>ซิงก์รูป: <?= e($event['title']) ?></h1>

<?php if ($error): ?>
    <div class="notice error"><?= e($error) ?></div>
<?php endif; ?>
<?php foreach ($failures as $failure): ?>
    <div class="notice error"><?= e($failure) ?></div>
<?php endforeach; ?>

<div class="form-card">
    <p><strong>โฟลเดอร์ต้นทาง:</strong><br><code><?= e($event['drive_folder_id']) ?></code></p>
    <p><?= $folderReady ? '✅ ระบบอ่านโฟลเดอร์ได้' : '❌ ไม่พบโฟลเดอร์หรือไม่มีสิทธิ์อ่าน' ?></p>
    <p>ระบบจะคัดลอกเฉพาะรูปใหม่หรือรูปที่แก้ไขจาก Google Drive for desktop แล้วตรวจจับทุกใบหน้าในแต่ละรูป</p>
    <p>ไม่ใช้ Google Cloud, Drive API, Client ID หรือ Client Secret</p>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
        <button class="btn" <?= $folderReady ? '' : 'disabled' ?>>เริ่มซิงก์และทำดัชนี</button>
    </form>
</div>
<?php page_footer(); ?>