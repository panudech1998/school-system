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
        $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach (['site_title', 'welcome_text', 'privacy_text'] as $key) {
            $stmt->execute([$key, trim((string) ($_POST[$key] ?? ''))]);
        }
        $message = 'บันทึกการตั้งค่าแล้ว';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

page_header('ตั้งค่าเว็บไซต์', true);
require __DIR__ . '/_nav.php';
?>
<h1>ตั้งค่าหน้าเว็บไซต์</h1>
<?php if ($message): ?><div class="notice success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="form-card"><form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field"><label>ชื่อระบบ</label><input name="site_title" value="<?= e(setting('site_title', APP_NAME)) ?>"></div>
    <div class="field"><label>ข้อความต้อนรับ</label><textarea name="welcome_text" rows="3"><?= e(setting('welcome_text')) ?></textarea></div>
    <div class="field"><label>ข้อความความเป็นส่วนตัว</label><textarea name="privacy_text" rows="3"><?= e(setting('privacy_text')) ?></textarea></div>
    <button class="btn" type="submit">บันทึก</button>
</form></div>
<?php page_footer(); ?>
