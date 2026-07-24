<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_login();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? 'save');
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete') {
            require_admin();
            db()->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
            audit('delete_event', (string) $id);
            flash('success', 'ลบกิจกรรมแล้ว');
            redirect('admin/events.php');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $sourceFolder = trim((string) ($_POST['drive_folder_id'] ?? ''), " \t\n\r\0\x0B\"'");

        if ($title === '' || $sourceFolder === '') {
            throw new RuntimeException('กรุณากรอกชื่อกิจกรรมและตำแหน่งโฟลเดอร์รูป');
        }
        if (!is_dir($sourceFolder)) {
            throw new RuntimeException('ไม่พบโฟลเดอร์รูป กรุณาตรวจตำแหน่ง Google Drive for desktop');
        }
        if (!is_readable($sourceFolder)) {
            throw new RuntimeException('Apache ไม่มีสิทธิ์อ่านโฟลเดอร์รูปนี้');
        }

        $sourceFolder = realpath($sourceFolder) ?: $sourceFolder;
        $slug = trim((string) ($_POST['slug'] ?? '')) ?: 'event-' . date('YmdHis');
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $slug) ?? '', '-'));
        $threshold = (float) ($_POST['face_threshold'] ?? 0.72);

        if ($threshold < 0.50 || $threshold > 0.95) {
            throw new RuntimeException('ค่าความเหมือนต้องอยู่ระหว่าง 0.50-0.95');
        }

        $params = [
            $title,
            $slug,
            trim((string) ($_POST['description'] ?? '')),
            ($_POST['event_date'] ?? '') ?: null,
            $sourceFolder,
            $threshold,
            isset($_POST['is_active']) ? 1 : 0,
            isset($_POST['allow_download']) ? 1 : 0,
        ];

        if ($id) {
            $params[] = $id;
            db()->prepare('UPDATE events SET title=?,slug=?,description=?,event_date=?,drive_folder_id=?,face_threshold=?,is_active=?,allow_download=?,updated_at=NOW() WHERE id=?')->execute($params);
            audit('update_event', (string) $id);
        } else {
            db()->prepare('INSERT INTO events(title,slug,description,event_date,drive_folder_id,face_threshold,is_active,allow_download,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())')->execute($params);
            audit('create_event', $title);
        }

        flash('success', 'บันทึกกิจกรรมและโฟลเดอร์รูปแล้ว');
        redirect('admin/events.php');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$edit = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $statement = db()->prepare('SELECT * FROM events WHERE id = ?');
    $statement->execute([$editId]);
    $edit = $statement->fetch() ?: null;
}

$events = db()->query('SELECT e.*,(SELECT COUNT(*) FROM photos p WHERE p.event_id=e.id) photo_count FROM events e ORDER BY id DESC')->fetchAll();

page_header('กิจกรรม', true);
require __DIR__ . '/_nav.php';
?>
<h1>จัดการกิจกรรม</h1>

<?php if ($error): ?>
    <div class="notice error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

        <div class="field">
            <label>ชื่อกิจกรรม</label>
            <input name="title" value="<?= e($edit['title'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label>Slug</label>
            <input name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="sport-day-2026">
        </div>
        <div class="field">
            <label>รายละเอียด</label>
            <textarea name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea>
        </div>
        <div class="field">
            <label>วันที่จัดงาน</label>
            <input type="date" name="event_date" value="<?= e($edit['event_date'] ?? '') ?>">
        </div>
        <div class="field">
            <label>ตำแหน่งโฟลเดอร์รูปจาก Google Drive for desktop</label>
            <input name="drive_folder_id" value="<?= e($edit['drive_folder_id'] ?? '') ?>" placeholder="G:\My Drive\งานปัจฉิม 2569" required>
            <small>เปิด File Explorer เข้าโฟลเดอร์รูป แล้วคัดลอกตำแหน่งมาวาง ไม่ต้องใช้ Folder ID หรือลิงก์ Google Drive</small>
        </div>
        <div class="field">
            <label>ค่าความเหมือนขั้นต่ำ</label>
            <input type="number" min="0.50" max="0.95" step="0.01" name="face_threshold" value="<?= e((string) ($edit['face_threshold'] ?? '0.72')) ?>">
        </div>

        <label><input type="checkbox" name="is_active" <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>> แสดงกิจกรรม</label><br>
        <label><input type="checkbox" name="allow_download" <?= !isset($edit['allow_download']) || $edit['allow_download'] ? 'checked' : '' ?>> อนุญาตดาวน์โหลด</label>
        <p><button class="btn">บันทึก</button></p>
    </form>
</div>

<h2>กิจกรรมทั้งหมด</h2>
<div class="table-wrap">
    <table>
        <thead><tr><th>กิจกรรม</th><th>โฟลเดอร์</th><th>รูป</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <?php $folderReady = is_dir((string) $event['drive_folder_id']) && is_readable((string) $event['drive_folder_id']); ?>
            <tr>
                <td><?= e($event['title']) ?></td>
                <td><small><?= e($event['drive_folder_id']) ?></small><br><?= $folderReady ? '<span class="notice success">พร้อม</span>' : '<span class="notice error">ไม่พบ</span>' ?></td>
                <td><?= (int) $event['photo_count'] ?></td>
                <td><?= $event['is_active'] ? 'แสดง' : 'ซ่อน' ?></td>
                <td>
                    <div class="actions">
                        <a class="btn secondary" href="?edit=<?= (int) $event['id'] ?>">แก้ไข</a>
                        <a class="btn" href="<?= e(url('admin/sync.php?event=' . $event['id'])) ?>">ซิงก์</a>
                        <a class="btn secondary" href="<?= e(url('admin/qr.php?event=' . $event['id'])) ?>">QR</a>
                        <form method="post" onsubmit="return confirm('ยืนยันลบกิจกรรมและข้อมูลรูป?')">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                            <button class="btn danger">ลบ</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php page_footer(); ?>