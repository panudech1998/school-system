<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$statusPath = STORAGE_PATH . '/web-auto-sync-status.json';
$status = [];

if (is_file($statusPath)) {
    $decoded = json_decode((string) file_get_contents($statusPath), true);
    if (is_array($decoded)) {
        $status = $decoded;
    }
}

$lastFinished = (int) ($status['finished_unix'] ?? 0);
$running = (bool) ($status['running'] ?? false);
$shouldStart = !$running && (time() - $lastFinished >= 10);
$started = false;

if ($shouldStart) {
    $phpExecutable = 'C:\\xampp\\php\\php.exe';
    if (!is_file($phpExecutable)) {
        $phpExecutable = PHP_BINARY;
    }

    $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'sync-once.php';

    if (is_file($phpExecutable) && is_file($script)) {
        if (PHP_OS_FAMILY === 'Windows') {
            $command = sprintf(
                'start "" /B "%s" "%s" >NUL 2>&1',
                str_replace('"', '', $phpExecutable),
                str_replace('"', '', $script)
            );
            $process = @popen($command, 'r');
            if (is_resource($process)) {
                @pclose($process);
                $started = true;
            }
        } else {
            $command = escapeshellarg($phpExecutable) . ' ' . escapeshellarg($script) . ' >/dev/null 2>&1 &';
            @exec($command);
            $started = true;
        }
    }
}

$photoCount = (int) db()->query(
    'SELECT COUNT(*) FROM photos p JOIN events e ON e.id=p.event_id WHERE p.is_visible=1 AND e.is_active=1'
)->fetchColumn();
$latestPhotoId = (int) db()->query(
    'SELECT COALESCE(MAX(p.id),0) FROM photos p JOIN events e ON e.id=p.event_id WHERE p.is_visible=1 AND e.is_active=1'
)->fetchColumn();

 echo json_encode([
    'ok' => true,
    'started' => $started,
    'running' => $running || $started,
    'photo_count' => $photoCount,
    'latest_photo_id' => $latestPhotoId,
    'last_finished' => $status['finished_at'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
