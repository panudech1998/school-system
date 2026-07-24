<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/google_drive.php';
require_admin();

function save_google_config(string $clientId, string $clientSecret, string $redirectUri): void
{
    $configPath = dirname(__DIR__) . '/config/app.local.php';
    $configDir = dirname($configPath);

    if (!is_dir($configDir) || !is_writable($configDir)) {
        throw new RuntimeException('โฟลเดอร์ config ไม่มีสิทธิ์เขียน กรุณาอนุญาตให้ Apache เขียนโฟลเดอร์ config');
    }

    $values = [
        'APP_NAME' => APP_NAME,
        'BASE_URL' => BASE_URL,
        'DB_HOST' => DB_HOST,
        'DB_PORT' => DB_PORT,
        'DB_NAME' => DB_NAME,
        'DB_USER' => DB_USER,
        'DB_PASS' => DB_PASS,
        'GOOGLE_CLIENT_ID' => $clientId,
        'GOOGLE_CLIENT_SECRET' => $clientSecret,
        'GOOGLE_REDIRECT_URI' => $redirectUri,
        'FACE_SERVICE_URL' => FACE_SERVICE_URL,
        'FACE_SERVICE_TOKEN' => FACE_SERVICE_TOKEN,
        'MAX_UPLOAD_BYTES' => MAX_UPLOAD_BYTES,
        'SESSION_NAME' => SESSION_NAME,
        'DEFAULT_ADMIN_EMAIL' => DEFAULT_ADMIN_EMAIL,
        'DEFAULT_ADMIN_PASSWORD' => DEFAULT_ADMIN_PASSWORD,
    ];

    $content = "<?php\n\ndeclare(strict_types=1);\n\n";
    $content .= "// สร้างอัตโนมัติจากหน้าหลังบ้าน ห้ามนำไฟล์นี้ขึ้น GitHub\n";
    foreach ($values as $name => $value) {
        $content .= "define('{$name}', " . var_export($value, true) . ");\n";
    }

    $temporary = $configPath . '.tmp';
    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        throw new RuntimeException('บันทึกไฟล์ config/app.local.php ไม่สำเร็จ');
    }

    if (!@rename($temporary, $configPath)) {
        @unlink($temporary);
        throw new RuntimeException('แทนที่ไฟล์ config/app.local.php ไม่สำเร็จ');
    }

    @chmod($configPath, 0600);
}

$error = '';

try {
    if (isset($_GET['code'])) {
        if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_GET['state'] ?? ''))) {
            throw new RuntimeException('OAuth state ไม่ถูกต้อง กรุณาเริ่มเชื่อมต่อใหม่');
        }

        drive_save_token(drive_exchange_code((string) $_GET['code']));
        audit('connect_drive');
        flash('success', 'เชื่อมต่อ Google Drive สำเร็จ');
        redirect('admin/google-drive.php');
    }

    if (($_GET['action'] ?? '') === 'connect') {
        if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
            throw new RuntimeException('กรุณาบันทึก Client ID และ Client Secret ก่อนเชื่อมต่อ');
        }

        header('Location: ' . drive_authorize_url());
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save_credentials') {
            $clientId = trim((string) ($_POST['client_id'] ?? ''));
            $clientSecretInput = trim((string) ($_POST['client_secret'] ?? ''));
            $redirectUri = trim((string) ($_POST['redirect_uri'] ?? ''));
            $clientSecret = $clientSecretInput !== '' ? $clientSecretInput : GOOGLE_CLIENT_SECRET;

            if ($clientId === '' || $clientSecret === '') {
                throw new RuntimeException('กรุณากรอก Client ID และ Client Secret ให้ครบ');
            }
            if (!str_ends_with($clientId, '.apps.googleusercontent.com')) {
                throw new RuntimeException('Client ID ไม่ถูกต้อง ต้องลงท้ายด้วย .apps.googleusercontent.com');
            }
            if (!filter_var($redirectUri, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Redirect URI ไม่ถูกต้อง');
            }

            save_google_config($clientId, $clientSecret, $redirectUri);
            audit('save_drive_credentials');
            flash('success', 'บันทึก Google OAuth แล้ว กรุณากดเชื่อมต่อบัญชี Google');
            redirect('admin/google-drive.php');
        }

        if ($action === 'disconnect') {
            db()->exec('DELETE FROM drive_connections WHERE id = 1');
            audit('disconnect_drive');
            flash('success', 'ยกเลิกการเชื่อมต่อแล้ว');
            redirect('admin/google-drive.php');
        }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$connection = drive_connection();
$configured = GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';

page_header('Google Drive', true);
require __DIR__ . '/_nav.php';
?>
<h1>เชื่อมต่อ Google Drive</h1>

<?php if ($error): ?>
    <div class="notice error"><?= e($error) ?></div>
<?php endif; ?>

<div class="form-card">
    <h2>ตั้งค่า Google OAuth</h2>
    <p>สร้าง OAuth Client ประเภท <strong>Web application</strong> ใน Google Cloud แล้วนำค่ามากรอกด้านล่าง</p>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_credentials">

        <div class="field">
            <label for="client_id">Client ID</label>
            <input id="client_id" name="client_id" value="<?= e(GOOGLE_CLIENT_ID) ?>" placeholder="1234567890-xxxx.apps.googleusercontent.com" required>
        </div>

        <div class="field">
            <label for="client_secret">Client Secret</label>
            <input id="client_secret" type="password" name="client_secret" placeholder="<?= GOOGLE_CLIENT_SECRET !== '' ? 'บันทึกแล้ว เว้นว่างเพื่อใช้ค่าเดิม' : 'กรอก Client Secret' ?>" <?= GOOGLE_CLIENT_SECRET === '' ? 'required' : '' ?>>
        </div>

        <div class="field">
            <label for="redirect_uri">Authorized redirect URI</label>
            <input id="redirect_uri" name="redirect_uri" value="<?= e(GOOGLE_REDIRECT_URI) ?>" required>
        </div>

        <button class="btn" type="submit">บันทึกการตั้งค่า</button>
    </form>
</div>

<div class="form-card" style="margin-top:20px">
    <h2>สถานะการเชื่อมต่อ</h2>
    <p><strong>Redirect URI ที่ต้องเพิ่มใน Google Cloud:</strong><br><code><?= e(GOOGLE_REDIRECT_URI) ?></code></p>

    <?php if (!$configured): ?>
        <div class="notice error">ยังไม่ได้ตั้งค่า Client ID และ Client Secret</div>
    <?php elseif ($connection): ?>
        <div class="notice success">เชื่อมต่อแล้ว Token หมดอายุ <?= e($connection['expires_at']) ?></div>
        <div class="actions">
            <a class="btn" href="?action=connect">เชื่อมต่อใหม่</a>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="disconnect">
                <button class="btn danger" type="submit">ยกเลิกการเชื่อมต่อ</button>
            </form>
        </div>
    <?php else: ?>
        <div class="notice">ตั้งค่า OAuth แล้ว แต่ยังไม่ได้เชื่อมบัญชี Google</div>
        <a class="btn" href="?action=connect">เชื่อมต่อบัญชี Google</a>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
