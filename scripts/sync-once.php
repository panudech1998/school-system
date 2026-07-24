<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../includes/local_sync.php';

set_time_limit(0);

$lockPath = STORAGE_PATH . '/web-auto-sync.lock';
$statusPath = STORAGE_PATH . '/web-auto-sync-status.json';
$lockHandle = fopen($lockPath, 'c+');

if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$status = [
    'running' => true,
    'started_at' => date(DATE_ATOM),
    'finished_at' => null,
    'updated' => 0,
    'indexed' => 0,
    'hidden' => 0,
    'errors' => [],
];

$writeStatus = static function (array $data) use ($statusPath): void {
    $temporary = $statusPath . '.tmp';
    @file_put_contents(
        $temporary,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
    @rename($temporary, $statusPath);
};

$writeStatus($status);

try {
    $events = db()->query('SELECT * FROM events WHERE is_active = 1 ORDER BY id')->fetchAll();

    foreach ($events as $event) {
        try {
            $result = sync_event_folder($event, true);
            $status['updated'] += (int) ($result['updated'] ?? 0);
            $status['indexed'] += (int) ($result['indexed'] ?? 0);
            $status['hidden'] += (int) ($result['hidden'] ?? 0);

            foreach (($result['failed'] ?? []) as $failure) {
                $status['errors'][] = (string) $event['title'] . ': ' . $failure;
            }
        } catch (Throwable $exception) {
            $status['errors'][] = (string) $event['title'] . ': ' . $exception->getMessage();
        }
    }
} catch (Throwable $exception) {
    $status['errors'][] = $exception->getMessage();
}

$status['running'] = false;
$status['finished_at'] = date(DATE_ATOM);
$status['finished_unix'] = time();
$writeStatus($status);

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
