<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../includes/local_sync.php';

set_time_limit(0);
$interval = isset($argv[1]) ? (int) $argv[1] : 15;
$interval = min(max($interval, 5), 3600);
$lockPath = STORAGE_PATH . '/auto-sync.lock';
$logPath = STORAGE_PATH . '/auto-sync.log';
$statusPath = auto_sync_status_path();
$lockHandle = fopen($lockPath, 'c+');

if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Auto Sync กำลังทำงานอยู่แล้ว\n");
    exit(1);
}

$startedAt = date(DATE_ATOM);

function auto_sync_log(string $message): void
{
    global $logPath;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    echo $line . PHP_EOL;
    @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function write_auto_sync_status(array $status): void
{
    global $statusPath;
    $temporary = $statusPath . '.tmp';
    @file_put_contents(
        $temporary,
        json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
    @rename($temporary, $statusPath);
}

auto_sync_log("เริ่ม Auto Sync ทุก {$interval} วินาที");
auto_sync_log('กด Ctrl+C เพื่อหยุด');

while (true) {
    $cycleResults = [];
    $cycleError = '';

    try {
        $events = db()->query('SELECT * FROM events WHERE is_active = 1 ORDER BY id')->fetchAll();

        foreach ($events as $event) {
            $eventId = (int) $event['id'];
            $title = (string) $event['title'];
            try {
                $result = sync_event_folder($event, true);
                $cycleResults[] = [
                    'event_id' => $eventId,
                    'title' => $title,
                    'result' => $result,
                    'checked_at' => date(DATE_ATOM),
                ];

                if ($result['updated'] > 0 || $result['indexed'] > 0 || $result['hidden'] > 0 || $result['failed']) {
                    auto_sync_log(sprintf(
                        '%s: พบ %d, อัปเดต %d, ทำดัชนี %d, ซ่อน %d, ผิดพลาด %d',
                        $title,
                        $result['discovered'],
                        $result['updated'],
                        $result['indexed'],
                        $result['hidden'],
                        count($result['failed'])
                    ));
                    audit('auto_sync_event', json_encode([
                        'event_id' => $eventId,
                        'updated' => $result['updated'],
                        'indexed' => $result['indexed'],
                        'hidden' => $result['hidden'],
                        'failed' => count($result['failed']),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }

                foreach ($result['failed'] as $failure) {
                    auto_sync_log($title . ': ' . $failure);
                }
            } catch (Throwable $exception) {
                $message = $title . ': ' . $exception->getMessage();
                auto_sync_log($message);
                $cycleResults[] = [
                    'event_id' => $eventId,
                    'title' => $title,
                    'error' => $exception->getMessage(),
                    'checked_at' => date(DATE_ATOM),
                ];
            }
        }
    } catch (Throwable $exception) {
        $cycleError = $exception->getMessage();
        auto_sync_log('ไม่สามารถตรวจรายการกิจกรรมได้: ' . $cycleError);
    }

    write_auto_sync_status([
        'running' => true,
        'pid' => getmypid(),
        'started_at' => $startedAt,
        'last_check' => date(DATE_ATOM),
        'last_check_unix' => time(),
        'interval_seconds' => $interval,
        'cycle_error' => $cycleError,
        'events' => $cycleResults,
    ]);

    sleep($interval);
}
