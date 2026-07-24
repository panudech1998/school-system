<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$events = db()->query(
    "SELECT e.*,
        (SELECT p.local_path FROM photos p WHERE p.event_id=e.id AND p.is_visible=1 ORDER BY p.id DESC LIMIT 1) cover_path,
        (SELECT COUNT(*) FROM photos p WHERE p.event_id=e.id AND p.is_visible=1) photo_count
     FROM events e
     WHERE e.is_active=1
     ORDER BY e.event_date DESC,e.id DESC"
)->fetchAll();

$latest = db()->query(
    "SELECT p.file_name,p.local_path,e.title event_title
     FROM photos p
     JOIN events e ON e.id=p.event_id
     WHERE p.is_visible=1 AND e.is_active=1
     ORDER BY p.id DESC
     LIMIT 12"
)->fetchAll();

$initialPhotoCount = (int) db()->query(
    'SELECT COUNT(*) FROM photos p JOIN events e ON e.id=p.event_id WHERE p.is_visible=1 AND e.is_active=1'
)->fetchColumn();
$initialLatestPhotoId = (int) db()->query(
    'SELECT COALESCE(MAX(p.id),0) FROM photos p JOIN events e ON e.id=p.event_id WHERE p.is_visible=1 AND e.is_active=1'
)->fetchColumn();

page_header('หน้าหลัก');
?>
<section class="hero">
    <h1><?= e(setting('site_title', APP_NAME)) ?></h1>
    <p><?= e(setting('welcome_text', 'ค้นหาและดาวน์โหลดภาพกิจกรรมของคุณ')) ?></p>
    <p><?= e(setting('privacy_text', 'ระบบไม่เก็บรูปเซลฟีไว้ถาวร')) ?></p>
</section>

<div id="sync-message" class="notice" style="display:none"></div>

<h2>เลือกกิจกรรม</h2>
<div class="grid">
    <?php foreach ($events as $event): ?>
        <article class="card">
            <?php if ($event['cover_path']): ?>
                <img src="<?= e(url($event['cover_path'])) ?>" alt="<?= e($event['title']) ?>">
            <?php endif; ?>
            <div class="card-body">
                <h3><?= e($event['title']) ?></h3>
                <p><?= e($event['description']) ?></p>
                <p><?= number_format((int) $event['photo_count']) ?> รูป</p>
                <div class="actions">
                    <a class="btn" href="<?= e(url('find.php?event=' . $event['id'])) ?>">ค้นหาด้วยใบหน้า</a>
                    <a class="btn secondary" href="<?= e(url('event.php?id=' . $event['id'])) ?>">ดูอัลบั้ม</a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php if (!$events): ?>
    <div class="notice">ยังไม่มีกิจกรรม</div>
<?php endif; ?>

<?php if ($latest): ?>
    <h2>รูปล่าสุด</h2>
    <div class="photo-grid">
        <?php foreach ($latest as $photo): ?>
            <article class="photo">
                <img loading="lazy" src="<?= e(url($photo['local_path'])) ?>" alt="<?= e($photo['file_name']) ?>">
                <div class="meta"><?= e($photo['event_title']) ?></div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
(() => {
    const initialCount = <?= $initialPhotoCount ?>;
    const initialLatestId = <?= $initialLatestPhotoId ?>;
    const endpoint = <?= json_encode(url('api/auto-sync-trigger.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const message = document.getElementById('sync-message');
    let checking = false;

    async function checkForNewPhotos() {
        if (checking || document.hidden) return;
        checking = true;

        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin'
            });
            if (!response.ok) return;

            const data = await response.json();
            if (data.started && message) {
                message.textContent = 'กำลังตรวจสอบรูปใหม่จากโฟลเดอร์...';
                message.style.display = 'block';
            }

            const countChanged = Number(data.photo_count) !== initialCount;
            const latestChanged = Number(data.latest_photo_id) !== initialLatestId;
            if (countChanged || latestChanged) {
                if (message) {
                    message.textContent = 'พบรูปใหม่ กำลังอัปเดตหน้าเว็บ...';
                    message.style.display = 'block';
                }
                window.setTimeout(() => window.location.reload(), 1000);
            }
        } catch (error) {
            // Background sync is best-effort; the page remains usable when unavailable.
        } finally {
            checking = false;
        }
    }

    checkForNewPhotos();
    window.setInterval(checkForNewPhotos, 15000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) checkForNewPhotos();
    });
})();
</script>
<?php page_footer(); ?>
