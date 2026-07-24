<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = db()->prepare('SELECT * FROM events WHERE id = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$id]);
$event = $stmt->fetch();
if (!$event) {
    http_response_code(404);
    exit('ไม่พบกิจกรรม');
}

$stmt = db()->prepare('SELECT * FROM photos WHERE event_id = ? AND is_visible = 1 ORDER BY id DESC');
$stmt->execute([$event['id']]);
$photos = $stmt->fetchAll();
$findPath = 'find.php?event=' . $event['id'];
$findUrl = absolute_url($findPath);

page_header($event['title']);
?>
<div class="actions">
    <a class="btn" href="<?= e(url($findPath)) ?>">ค้นหารูปของฉัน</a>
    <a class="btn secondary" href="<?= e(url()) ?>">กลับหน้าหลัก</a>
</div>
<h1><?= e($event['title']) ?></h1>
<p><?= e($event['description']) ?></p>
<div class="card" style="max-width:220px;margin-bottom:24px"><div class="card-body"><strong>QR ค้นหาด้วยใบหน้า</strong><div class="qr" data-qr="<?= e($findUrl) ?>"></div></div></div>
<div class="photo-grid">
<?php foreach ($photos as $photo): ?>
    <?php $downloadPath = 'download.php?id=' . $photo['id']; ?>
    <article class="photo">
        <img loading="lazy" src="<?= e(url($photo['local_path'])) ?>" alt="<?= e($photo['file_name']) ?>">
        <div class="meta">
            <strong><?= e($photo['file_name']) ?></strong>
            <?php if ((int) $event['allow_download'] === 1): ?>
                <p><a class="btn" href="<?= e(url($downloadPath)) ?>">ดาวน์โหลด</a></p>
                <div class="qr" data-qr="<?= e(absolute_url($downloadPath)) ?>"></div>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php if (!$photos): ?><div class="notice">กิจกรรมนี้ยังไม่มีรูปที่ซิงก์แล้ว</div><?php endif; ?>
<?php page_footer(); ?>
