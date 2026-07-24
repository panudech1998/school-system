<?php

declare(strict_types=1);

$localConfig = __DIR__ . '/app.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

defined('APP_NAME') || define('APP_NAME', 'SWK_Phonto');
defined('BASE_URL') || define('BASE_URL', '/SWK_Phonto');
defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_PORT') || define('DB_PORT', '3306');
defined('DB_NAME') || define('DB_NAME', 'swk_phonto');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('GOOGLE_CLIENT_ID') || define('GOOGLE_CLIENT_ID', '');
defined('GOOGLE_CLIENT_SECRET') || define('GOOGLE_CLIENT_SECRET', '');
defined('GOOGLE_REDIRECT_URI') || define('GOOGLE_REDIRECT_URI', 'http://localhost/SWK_Phonto/admin/google-drive.php');
defined('FACE_SERVICE_URL') || define('FACE_SERVICE_URL', 'http://127.0.0.1:5055');
defined('FACE_SERVICE_TOKEN') || define('FACE_SERVICE_TOKEN', 'change-this-face-service-token');
defined('STORAGE_PATH') || define('STORAGE_PATH', __DIR__ . '/../storage');
defined('MAX_UPLOAD_BYTES') || define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);
defined('SESSION_NAME') || define('SESSION_NAME', 'swk_phonto_session');
defined('DEFAULT_ADMIN_EMAIL') || define('DEFAULT_ADMIN_EMAIL', 'admin@swk.local');
defined('DEFAULT_ADMIN_PASSWORD') || define('DEFAULT_ADMIN_PASSWORD', '12345678');
