<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/google_drive.php';
require_admin();

$message = '';
$error = '';

try {
    if (isset($_GET['code'])) {
        if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_GET['state'] ?? ''))) {
            throw new RuntimeException('OAuth state ไม่ถูกต้อง กรุณาเริ่มเชื่อมต่อใหม่');
        }
        drive_save_token(drive_exchange_code((string) $_GET['code']));
        $message = 'เชื่อมต่อ Google Drive สำเร็จ';
    } elseif (($_GET['action'] ?? '') === 'connect') {
        if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
            throw new RuntimeException('กรุณากำหนด GOOGLE_CLIENT_ID และ GOOGLE_CLIENT_SECRET ที่ config/app.php ก่อน');
        }
        header('Location: ' . drive_authorize_url());
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'disconnect') {
        verify_csrf();
        db()->exec('DELETE FROM drive_connections WHERE id = 1');
        $message = 'ยกเลิกการเชื่อมต่อแล้ว';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$connection = drive_connection();
page_header('Google Drive', true);
require __DIR__ . '/_nav.php';
?>
<h1>เชื่อมต่อ Google Drive</h1>
<?php if ($message): ?><div class="notice success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="form-card">
    <p><strong>Redirect URI:</strong> <?= e(GOOGLE_REDIRECT_URI) ?></p>
    <p>เปิด Google Drive API ใน Google Cloud แล้วสร้าง OAuth Client ประเภท Web application จากนั้นนำ Client ID และ Client Secret ใส่ใน <code>config/app.php</code></p>
    <?php if ($connection): ?>
        <div class="notice success">เชื่อมต่อแล้ว Token หมดอายุ: <?= e($connection['expires_at']) ?></div>
        <div class="actions">
            <a class="btn" href="?action=connect">เชื่อมต่อใหม่</a>
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="disconnect"><button class="btn danger" type="submit">ยกเลิกการเชื่อมต่อ</button></form>
        </div>
    <?php else: ?>
        <a class="btn" href="?action=connect">เชื่อมต่อบัญชี Google</a>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
