<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $folderId = trim((string) ($_POST['drive_folder_id'] ?? ''));
        $threshold = (float) ($_POST['face_threshold'] ?? 0.72);
        if ($title === '' || $folderId === '') {
            throw new RuntimeException('กรุณากรอกชื่อกิจกรรมและ Google Drive Folder ID');
        }
        if ($slug === '') {
            $slug = 'event-' . date('YmdHis');
        }
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $slug) ?: 'event-' . time());
        if ($threshold < 0.50 || $threshold > 0.95) {
            throw new RuntimeException('ค่าความเหมือนต้องอยู่ระหว่าง 0.50 ถึง 0.95');
        }
        $params = [
            $title,
            $slug,
            trim((string) ($_POST['description'] ?? '')),
            ($_POST['event_date'] ?? '') ?: null,
            $folderId,
            $threshold,
            isset($_POST['is_active']) ? 1 : 0,
            isset($_POST['allow_download']) ? 1 : 0,
        ];
        if ($id > 0) {
            $stmt = db()->prepare('UPDATE events SET title=?,slug=?,description=?,event_date=?,drive_folder_id=?,face_threshold=?,is_active=?,allow_download=?,updated_at=NOW() WHERE id=?');
            $params[] = $id;
            $stmt->execute($params);
            $message = 'บันทึกการแก้ไขกิจกรรมแล้ว';
        } else {
            $stmt = db()->prepare('INSERT INTO events (title,slug,description,event_date,drive_folder_id,face_threshold,is_active,allow_download,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())');
            $stmt->execute($params);
            $message = 'เพิ่มกิจกรรมแล้ว';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$edit = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: null;
}
$events = db()->query('SELECT e.*, (SELECT COUNT(*) FROM photos p WHERE p.event_id=e.id) photo_count FROM events e ORDER BY e.id DESC')->fetchAll();

page_header('จัดการกิจกรรม', true);
require __DIR__ . '/_nav.php';
?>
<h1>จัดการกิจกรรมและโฟลเดอร์ Google Drive</h1>
<?php if ($message): ?><div class="notice success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="form-card">
<form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="field"><label>ชื่อกิจกรรม</label><input name="title" value="<?= e($edit['title'] ?? '') ?>" required></div>
    <div class="field"><label>Slug ภาษาอังกฤษ</label><input name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="sport-day-2026"></div>
    <div class="field"><label>รายละเอียด</label><textarea name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea></div>
    <div class="field"><label>วันที่จัดงาน</label><input type="date" name="event_date" value="<?= e($edit['event_date'] ?? '') ?>"></div>
    <div class="field"><label>Google Drive Folder ID</label><input name="drive_folder_id" value="<?= e($edit['drive_folder_id'] ?? '') ?>" required></div>
    <div class="field"><label>ค่าความเหมือนขั้นต่ำ (แนะนำ 0.72)</label><input type="number" min="0.50" max="0.95" step="0.01" name="face_threshold" value="<?= e((string) ($edit['face_threshold'] ?? '0.72')) ?>"></div>
    <label><input type="checkbox" name="is_active" <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>> แสดงกิจกรรม</label><br>
    <label><input type="checkbox" name="allow_download" <?= !isset($edit['allow_download']) || $edit['allow_download'] ? 'checked' : '' ?>> อนุญาตดาวน์โหลด</label>
    <p><button class="btn" type="submit">บันทึก</button></p>
</form>
</div>
<h2>กิจกรรมทั้งหมด</h2>
<div class="table-wrap"><table><thead><tr><th>กิจกรรม</th><th>รูป</th><th>เกณฑ์</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>
<?php foreach ($events as $event): ?>
<tr>
    <td><?= e($event['title']) ?></td><td><?= (int) $event['photo_count'] ?></td><td><?= e($event['face_threshold']) ?></td><td><?= $event['is_active'] ? 'แสดง' : 'ซ่อน' ?></td>
    <td class="actions"><a class="btn secondary" href="?edit=<?= (int) $event['id'] ?>">แก้ไข</a><a class="btn" href="<?= e(url('admin/sync.php?event=' . $event['id'])) ?>">ซิงก์รูป</a><a class="btn secondary" target="_blank" href="<?= e(url('find.php?event=' . $event['id'])) ?>">หน้าค้นหา</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php page_footer(); ?>
