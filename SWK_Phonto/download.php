<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = db()->prepare(
    'SELECT p.*, e.allow_download, e.is_active
     FROM photos p JOIN events e ON e.id = p.event_id
     WHERE p.id = ? AND p.is_visible = 1 LIMIT 1'
);
$stmt->execute([$id]);
$photo = $stmt->fetch();
if (!$photo || !(int) $photo['allow_download'] || !(int) $photo['is_active']) {
    http_response_code(404);
    exit('ไม่พบรูปหรือไม่ได้รับอนุญาตให้ดาวน์โหลด');
}

$fullPath = realpath(__DIR__ . '/' . $photo['local_path']);
$storageRoot = realpath(STORAGE_PATH);
if (!$fullPath || !$storageRoot || !str_starts_with($fullPath, $storageRoot) || !is_file($fullPath)) {
    http_response_code(404);
    exit('ไม่พบไฟล์');
}

$log = db()->prepare('INSERT INTO download_logs (photo_id, ip_hash, created_at) VALUES (?, ?, NOW())');
$log->execute([(int) $photo['id'], hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . session_id())]);

header('Content-Type: ' . $photo['mime_type']);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($photo['file_name']));
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
