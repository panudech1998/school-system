<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
function current_user(): ?array { if(empty($_SESSION['user_id']))return null;$s=db()->prepare('SELECT id,name,email,role,is_active FROM users WHERE id=? AND is_active=1 LIMIT 1');$s->execute([(int)$_SESSION['user_id']]);return $s->fetch()?:null; }
function require_login(): array { $u=current_user();if(!$u){flash('error','กรุณาเข้าสู่ระบบ');redirect('login.php');}return $u; }
function require_admin(): array { $u=require_login();if($u['role']!=='admin'){http_response_code(403);exit('ไม่มีสิทธิ์เข้าถึง');}return $u; }
