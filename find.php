<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$id = filter_input(INPUT_GET, 'event', FILTER_VALIDATE_INT);
$stmt = db()->prepare('SELECT * FROM events WHERE id = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$id]);
$event = $stmt->fetch();
if (!$event) {
    http_response_code(404);
    exit('ไม่พบกิจกรรม');
}

page_header('ค้นหารูปของฉัน');
?>
<div class="form-card">
    <h1>ค้นหารูปด้วยใบหน้า</h1>
    <h2><?= e($event['title']) ?></h2>
    <p>ถ่ายรูปหน้าตรงให้เห็นใบหน้าชัดเจน ระบบจะแสดงเฉพาะรูปที่ใบหน้าผ่านค่าความเหมือนของกิจกรรมนี้เท่านั้น</p>
    <div class="notice">รูปที่อัปโหลดใช้เพื่อค้นหาในครั้งนี้และบริการค้นหาจะลบไฟล์ชั่วคราวหลังประมวลผล</div>
    <form id="face-search-form" action="<?= e(url('api/face-search.php')) ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="field">
            <label for="selfie">ถ่ายรูปหรือเลือกรูปใบหน้า</label>
            <input id="selfie" name="selfie" type="file" accept="image/jpeg,image/png,image/webp" capture="user" required>
        </div>
        <label class="field"><span><input type="checkbox" name="consent" value="1" required> ยินยอมให้ประมวลผลใบหน้าเพื่อค้นหารูปในกิจกรรมนี้</span></label>
        <button class="btn" type="submit">เริ่มค้นหา</button>
    </form>
</div>
<section id="search-status" style="margin-top:24px"></section>
<section id="search-results" class="photo-grid"></section>
<?php page_footer(); ?>
