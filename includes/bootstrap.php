<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name(SESSION_NAME);
    session_set_cookie_params(['lifetime'=>0,'path'=>rtrim(BASE_URL,'/') ?: '/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path=''): string { return rtrim(BASE_URL,'/').'/'.ltrim($path,'/'); }
function absolute_url(string $path=''): string {
    $forwarded = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwarded === 'https') ? 'https' : 'http';
    $host = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    return $scheme.'://'.$host.url($path);
}
function redirect(string $path): never { header('Location: '.url($path)); exit; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return (string)$_SESSION['csrf']; }
function verify_csrf(): void { $token=$_POST['csrf']??''; if(!is_string($token)||!hash_equals((string)($_SESSION['csrf']??''),$token)){http_response_code(419);exit('CSRF token ไม่ถูกต้อง');} }
function setting(string $key,string $default=''): string { try{$s=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:(string)$v;}catch(Throwable){return $default;} }
function flash(string $type,string $message): void { $_SESSION['flash'][]=['type'=>$type,'message'=>$message]; }
function consume_flashes(): array { $items=$_SESSION['flash']??[];unset($_SESSION['flash']);return is_array($items)?$items:[]; }
function audit(string $action,string $detail=''): void { try{$s=db()->prepare('INSERT INTO audit_logs(user_id,action_name,detail_text,ip_hash,created_at) VALUES(?,?,?,?,NOW())');$s->execute([$_SESSION['user_id']??null,$action,$detail,hash('sha256',(string)($_SERVER['REMOTE_ADDR']??''))]);}catch(Throwable){} }
function ensure_default_admin(): void { try{if((int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn()===0){$s=db()->prepare('INSERT INTO users(name,email,password_hash,role,is_active,created_at,updated_at) VALUES(?,?,?,?,1,NOW(),NOW())');$s->execute(['ผู้ดูแลระบบ',DEFAULT_ADMIN_EMAIL,password_hash(DEFAULT_ADMIN_PASSWORD,PASSWORD_DEFAULT),'admin']);}}catch(Throwable){} }
ensure_default_admin();
