<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$eventId = filter_input(INPUT_GET, 'event', FILTER_VALIDATE_INT);

if ($eventId) {
    $statement = db()->prepare('SELECT * FROM events WHERE id=? AND is_active=1 LIMIT 1');
    $statement->execute([$eventId]);
    $event = $statement->fetch();
} else {
    $event = db()->query(
        'SELECT * FROM events WHERE is_active=1 ORDER BY event_date DESC, id DESC LIMIT 1'
    )->fetch();
}

if (!$event) {
    page_header('ค้นหารูปด้วยใบหน้า');
    ?>
    <div class="notice">ยังไม่มีกิจกรรมที่เปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ</div>
    <?php
    page_footer();
    exit;
}

page_header('ค้นหารูปของฉัน');
?>
<div class="form-card">
    <h1>ค้นหารูปด้วยใบหน้า</h1>
    <h2><?= e($event['title']) ?></h2>
    <p>ใช้ภาพหน้าตรงที่มีใบหน้าของผู้ค้นหาเพียงคนเดียว ระบบจะแสดงเฉพาะภาพที่ผ่านค่าความเหมือน</p>
    <div class="notice">รูปเซลฟีจะถูกลบจากไฟล์ชั่วคราวทันทีหลังประมวลผล</div>

    <form id="face-search-form" action="<?= e(url('api/face-search.php')) ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="field">
            <label for="selfie">ถ่ายรูปหรือเลือกรูปใบหน้า</label>
            <input id="selfie" name="selfie" type="file" accept="image/jpeg,image/png,image/webp" capture="user" required>
        </div>

        <label class="field">
            <span><input type="checkbox" name="consent" value="1" required> ยินยอมให้ประมวลผลใบหน้าเพื่อค้นหารูปครั้งนี้</span>
        </label>

        <button class="btn" type="submit">เริ่มค้นหา</button>
    </form>
</div>

<section id="search-status" style="margin-top:24px"></section>
<section id="search-results" class="photo-grid"></section>
<?php page_footer(); ?>
