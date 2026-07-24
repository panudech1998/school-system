<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/face_service.php';
require_once __DIR__ . '/../includes/local_sync.php';

$user = require_login();
$stats = [
    'events' => (int) db()->query('SELECT COUNT(*) FROM events')->fetchColumn(),
    'photos' => (int) db()->query('SELECT COUNT(*) FROM photos')->fetchColumn(),
    'indexed' => (int) db()->query('SELECT COUNT(*) FROM photos WHERE face_indexed=1')->fetchColumn(),
    'searches' => (int) db()->query('SELECT COUNT(*) FROM face_search_logs')->fetchColumn(),
    'downloads' => (int) db()->query('SELECT COUNT(*) FROM download_logs')->fetchColumn(),
];
$labels = [
    'events' => 'กิจกรรม',
    'photos' => 'รูปทั้งหมด',
    'indexed' => 'ทำดัชนีแล้ว',
    'searches' => 'การค้นหา',
    'downloads' => 'ดาวน์โหลด',
];

$eventFolders = db()->query('SELECT drive_folder_id FROM events')->fetchAll(PDO::FETCH_COLUMN);
$readyFolders = 0;
foreach ($eventFolders as $folder) {
    if (is_dir((string) $folder) && is_readable((string) $folder)) {
        $readyFolders++;
    }
}

$face = face_health();
$autoSyncStatus = read_auto_sync_status();
$autoSyncRunning = auto_sync_is_running($autoSyncStatus);
page_header('Dashboard', true);
require __DIR__ . '/_nav.php';
?>
<h1>Dashboard</h1>
<p>ยินดีต้อนรับ <?= e($user['name']) ?></p>

<div class="stats">
    <?php foreach ($stats as $key => $value): ?>
        <div class="stat"><span><?= e($labels[$key]) ?></span><strong><?= number_format($value) ?></strong></div>
    <?php endforeach; ?>
</div>

<h2>สถานะระบบ</h2>
<div class="grid">
    <div class="card">
        <div class="card-body">
            <h3>โฟลเดอร์ Google Drive</h3>
            <p>อ่านได้ <?= number_format($readyFolders) ?> จาก <?= number_format(count($eventFolders)) ?> กิจกรรม</p>
            <small>ใช้ Google Drive for desktop โดยไม่ผ่าน Google Cloud</small>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h3>Face Service</h3>
            <p><?= $face ? '✅ พร้อมใช้งาน' : '❌ ไม่พร้อมใช้งาน' ?></p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h3>Auto Sync</h3>
            <p><?= $autoSyncRunning ? '✅ กำลังทำงาน' : '❌ ยังไม่ได้เปิด' ?></p>
            <?php if ($autoSyncRunning): ?>
                <small>ตรวจล่าสุด <?= e((string) ($autoSyncStatus['last_check'] ?? '-')) ?></small>
            <?php else: ?>
                <small>เปิดด้วย START_AUTO_SYNC.bat หรือ START_SWK_PHONTO.bat</small>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php page_footer(); ?>
