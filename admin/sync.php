<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/local_sync.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $result = sync_event_folder($event, true);
        $_SESSION['sync_failures'] = $result['failed'];
        audit('sync_local_drive_folder', (string) $event['id']);
        flash(
            'success',
            "ตรวจพบ {$result['discovered']} รูป อัปเดต {$result['updated']} รูป "
            . "ทำดัชนี {$result['indexed']} รูป ซ่อน {$result['hidden']} รูป "
            . "และไม่มีการเปลี่ยนแปลง {$result['unchanged']} รูป"
        );
        redirect('admin/sync.php?event=' . $event['id']);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$failures = $_SESSION['sync_failures'] ?? [];
unset($_SESSION['sync_failures']);
$folderReady = is_dir((string) $event['drive_folder_id']) && is_readable((string) $event['drive_folder_id']);
$autoSyncStatus = read_auto_sync_status();
$autoSyncRunning = auto_sync_is_running($autoSyncStatus);

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
    <h2>ซิงก์อัตโนมัติ</h2>
    <?php if ($autoSyncRunning): ?>
        <div class="notice success">
            ✅ Auto Sync กำลังทำงาน ตรวจล่าสุด
            <?= e((string) ($autoSyncStatus['last_check'] ?? '-')) ?>
            ทุก <?= (int) ($autoSyncStatus['interval_seconds'] ?? 15) ?> วินาที
        </div>
        <p>เมื่อมีรูปใหม่หรือแก้ไขรูปในโฟลเดอร์ ระบบจะอัปเดตและทำดัชนีใบหน้าให้อัตโนมัติ</p>
    <?php else: ?>
        <div class="notice error">❌ Auto Sync ยังไม่ได้เปิด</div>
        <p>ดับเบิลคลิก <code>START_AUTO_SYNC.bat</code> หรือเปิดระบบทั้งหมดด้วย <code>START_SWK_PHONTO.bat</code></p>
    <?php endif; ?>
</div>

<div class="form-card" style="margin-top:20px">
    <h2>ซิงก์ด้วยตนเอง</h2>
    <p><strong>โฟลเดอร์ต้นทาง:</strong><br><code><?= e($event['drive_folder_id']) ?></code></p>
    <p><?= $folderReady ? '✅ ระบบอ่านโฟลเดอร์ได้' : '❌ ไม่พบโฟลเดอร์หรือไม่มีสิทธิ์อ่าน' ?></p>
    <p>ระบบจะคัดลอกเฉพาะรูปใหม่หรือรูปที่แก้ไขจาก Google Drive for desktop แล้วตรวจจับทุกใบหน้าในแต่ละรูป</p>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
        <button class="btn" <?= $folderReady ? '' : 'disabled' ?>>ซิงก์ตอนนี้</button>
    </form>
</div>
<?php page_footer(); ?>
