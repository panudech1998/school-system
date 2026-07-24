<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

if (!empty($_SESSION['user_id'])) {
    redirect('admin/');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            redirect('admin/');
        }
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

page_header('เข้าสู่ระบบ');
?>
<div class="form-card" style="margin:auto">
    <h1>เข้าสู่ระบบหลังบ้าน</h1>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="field"><label>อีเมล</label><input type="email" name="email" required></div>
        <div class="field"><label>รหัสผ่าน</label><input type="password" name="password" required></div>
        <button class="btn" type="submit">เข้าสู่ระบบ</button>
    </form>
</div>
<?php page_footer(); ?>
