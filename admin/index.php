<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_admin();
$stats = [
    'events' => (int) db()->query('SELECT COUNT(*) FROM events')->fetchColumn(),
    'photos' => (int) db()->query('SELECT COUNT(*) FROM photos')->fetchColumn(),
    'indexed' => (int) db()->query('SELECT COUNT(*) FROM photos WHERE face_indexed = 1')->fetchColumn(),
    'searches' => (int) db()->query('SELECT COUNT(*) FROM face_search_logs')->fetchColumn(),
    'downloads' => (int) db()->query('SELECT COUNT(*) FROM download_logs')->fetchColumn(),
];
$driveConnected = (bool) db()->query('SELECT COUNT(*) FROM drive_connections WHERE id = 1')->fetchColumn();

$faceStatus = 'ไม่พร้อม';
$ch = curl_init(rtrim(FACE_SERVICE_URL, '/') . '/health');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
if (curl_exec($ch) !== false && (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200) {
    $faceStatus = 'พร้อมใช้งาน';
}
curl_close($ch);

page_header('Dashboard', true);
require __DIR__ . '/_nav.php';
?>
<h1>Dashboard</h1>
<p>ยินดีต้อนรับ <?= e($user['name']) ?></p>
<div class="stats">
    <div class="stat"><span>กิจกรรม</span><strong><?= $stats['events'] ?></strong></div>
    <div class="stat"><span>รูปทั้งหมด</span><strong><?= $stats['photos'] ?></strong></div>
    <div class="stat"><span>ทำดัชนีใบหน้าแล้ว</span><strong><?= $stats['indexed'] ?></strong></div>
    <div class="stat"><span>การค้นหา</span><strong><?= $stats['searches'] ?></strong></div>
    <div class="stat"><span>ดาวน์โหลด</span><strong><?= $stats['downloads'] ?></strong></div>
</div>
<h2>สถานะการเชื่อมต่อ</h2>
<div class="grid">
    <div class="card"><div class="card-body"><h3>Google Drive</h3><p><?= $driveConnected ? 'เชื่อมต่อแล้ว' : 'ยังไม่ได้เชื่อมต่อ' ?></p></div></div>
    <div class="card"><div class="card-body"><h3>Face Service</h3><p><?= e($faceStatus) ?></p></div></div>
</div>
<?php page_footer(); ?>
