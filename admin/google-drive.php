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

function credentials_from_uploaded_json(array $upload): array
{
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดเกินค่าที่เซิร์ฟเวอร์อนุญาต',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินไป',
            UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์ไม่สมบูรณ์',
            UPLOAD_ERR_NO_FILE => 'กรุณาเลือกไฟล์ OAuth JSON',
        ];
        throw new RuntimeException($messages[$error] ?? 'อัปโหลดไฟล์ไม่สำเร็จ');
    }

    $size = (int) ($upload['size'] ?? 0);
    if ($size <= 0 || $size > 1024 * 1024) {
        throw new RuntimeException('ไฟล์ OAuth JSON ต้องมีขนาดไม่เกิน 1 MB');
    }

    $temporaryPath = (string) ($upload['tmp_name'] ?? '');
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new RuntimeException('ไม่พบไฟล์ที่อัปโหลด');
    }

    $raw = file_get_contents($temporaryPath);
    if ($raw === false) {
        throw new RuntimeException('อ่านไฟล์ OAuth JSON ไม่สำเร็จ');
    }

    try {
        $json = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('ไฟล์ที่เลือกไม่ใช่ OAuth JSON ที่ถูกต้อง');
    }

    $web = is_array($json) ? ($json['web'] ?? null) : null;
    if (!is_array($web)) {
        throw new RuntimeException('กรุณาใช้ OAuth Client ประเภท Web application ไม่ใช่ Desktop app');
    }

    $clientId = trim((string) ($web['client_id'] ?? ''));
    $clientSecret = trim((string) ($web['client_secret'] ?? ''));
    if ($clientId === '' || $clientSecret === '') {
        throw new RuntimeException('ไม่พบ Client ID หรือ Client Secret ในไฟล์ JSON');
    }
    if (!str_ends_with($clientId, '.apps.googleusercontent.com')) {
        throw new RuntimeException('Client ID ในไฟล์ JSON ไม่ถูกต้อง');
    }

    return [$clientId, $clientSecret];
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

    if (isset($_GET['error'])) {
        $oauthError = trim((string) $_GET['error']);
        throw new RuntimeException('Google ยกเลิกหรือปฏิเสธการเชื่อมต่อ: ' . ($oauthError ?: 'ไม่ทราบสาเหตุ'));
    }

    if (($_GET['action'] ?? '') === 'connect') {
        if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
            throw new RuntimeException('กรุณาอัปโหลดไฟล์ OAuth JSON ก่อนเชื่อมต่อ');
        }

        header('Location: ' . drive_authorize_url());
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'import_json') {
            [$clientId, $clientSecret] = credentials_from_uploaded_json($_FILES['credentials_json'] ?? []);
            save_google_config($clientId, $clientSecret, GOOGLE_REDIRECT_URI);
            audit('import_drive_credentials');
            redirect('admin/google-drive.php?action=connect');
        }

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
    <h2>เชื่อมต่อแบบง่าย</h2>
    <ol>
        <li>สร้าง OAuth Client ประเภท <strong>Web application</strong> ใน Google Cloud</li>
        <li>เพิ่ม Redirect URI ด้านล่าง แล้วดาวน์โหลดไฟล์ JSON</li>
        <li>อัปโหลดไฟล์ JSON ระบบจะบันทึกและเปิดหน้าเชื่อมต่อ Google ให้อัตโนมัติ</li>
    </ol>

    <div class="notice">
        <strong>Authorized redirect URI</strong><br>
        <code id="redirect-uri"><?= e(GOOGLE_REDIRECT_URI) ?></code>
        <button class="btn secondary" type="button" style="margin-left:8px" onclick="navigator.clipboard.writeText(document.getElementById('redirect-uri').textContent)">คัดลอก</button>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="import_json">

        <div class="field">
            <label for="credentials_json">ไฟล์ OAuth Client JSON</label>
            <input id="credentials_json" type="file" name="credentials_json" accept="application/json,.json" required>
            <small>เลือกไฟล์ที่ดาวน์โหลดจาก Google Cloud ระบบจะไม่เก็บไฟล์ JSON ต้นฉบับไว้</small>
        </div>

        <button class="btn" type="submit">อัปโหลดและเชื่อมต่อ Google Drive</button>
    </form>
</div>

<div class="form-card" style="margin-top:20px">
    <h2>สถานะการเชื่อมต่อ</h2>

    <?php if (!$configured): ?>
        <div class="notice error">ยังไม่ได้อัปโหลดไฟล์ OAuth JSON</div>
    <?php elseif ($connection): ?>
        <div class="notice success">เชื่อมต่อ Google Drive แล้ว</div>
        <p>Access token หมดอายุ <?= e($connection['expires_at']) ?> และระบบจะต่ออายุด้วย Refresh token เมื่อจำเป็น</p>
        <div class="actions">
            <a class="btn" href="?action=connect">เปลี่ยนบัญชี Google</a>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="disconnect">
                <button class="btn danger" type="submit">ยกเลิกการเชื่อมต่อ</button>
            </form>
        </div>
    <?php else: ?>
        <div class="notice">มีข้อมูล OAuth แล้ว แต่ยังไม่ได้เชื่อมบัญชี Google</div>
        <a class="btn" href="?action=connect">เชื่อมต่อบัญชี Google</a>
    <?php endif; ?>
</div>

<details class="form-card" style="margin-top:20px">
    <summary><strong>ตั้งค่าขั้นสูง: กรอก Client ID และ Client Secret เอง</strong></summary>
    <form method="post" autocomplete="off" style="margin-top:18px">
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

        <button class="btn secondary" type="submit">บันทึกแบบกำหนดเอง</button>
    </form>
</details>
<?php page_footer(); ?>
