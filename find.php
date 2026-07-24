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
<style>
.face-search-wrap{max-width:760px;margin:0 auto}.face-search-head{text-align:center;margin-bottom:22px}.face-search-head h1{margin-bottom:8px}.face-search-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:20px 0}.face-step{background:#eef4ff;border-radius:14px;padding:12px;text-align:center;font-size:.94rem}.capture-options{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:20px 0}.capture-option{border:2px solid #dbe4f0;border-radius:18px;padding:20px;text-align:center;background:#fff}.capture-option strong{display:block;font-size:1.08rem;margin:8px 0}.capture-option p{margin:0 0 14px;color:#5b6472;font-size:.92rem}.camera-panel{display:none;margin-top:18px;padding:14px;background:#111827;border-radius:18px}.camera-panel.active{display:block}.camera-video,.photo-preview{display:block;width:100%;max-height:480px;object-fit:cover;border-radius:14px;background:#111827}.camera-actions,.preview-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:12px}.preview-panel{display:none;margin-top:18px}.preview-panel.active{display:block}.privacy-note{background:#edf8f1;border:1px solid #ccebd7;border-radius:14px;padding:13px;margin:16px 0}.search-submit{width:100%;font-size:1.08rem;padding:14px}.hidden-file{position:absolute;width:1px;height:1px;overflow:hidden;opacity:0}.consent-box{display:flex;gap:10px;align-items:flex-start;background:#f7f9fc;border-radius:12px;padding:14px;margin:18px 0}.consent-box input{margin-top:4px}.camera-error{display:none;margin-top:12px}.camera-error.active{display:block}@media(max-width:640px){.capture-options{grid-template-columns:1fr}.face-search-steps{grid-template-columns:1fr}.face-search-wrap{padding:0 2px}}
</style>

<div class="face-search-wrap">
    <div class="face-search-head">
        <h1>ค้นหารูปของคุณด้วยใบหน้า</h1>
        <p>กิจกรรม: <strong><?= e($event['title']) ?></strong></p>
        <p>ถ่ายภาพหน้าตรงหรือเลือกรูปที่เห็นใบหน้าชัดเจนเพียงคนเดียว</p>
    </div>

    <div class="face-search-steps" aria-label="ขั้นตอนการค้นหา">
        <div class="face-step"><strong>1</strong><br>ถ่ายหรือเลือกรูป</div>
        <div class="face-step"><strong>2</strong><br>ตรวจสอบภาพ</div>
        <div class="face-step"><strong>3</strong><br>ค้นหารูปที่ตรงกัน</div>
    </div>

    <div class="form-card">
        <form id="face-search-form" action="<?= e(url('api/face-search.php')) ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input id="selfie" class="hidden-file" name="selfie" type="file" accept="image/jpeg,image/png,image/webp" required>

            <div class="capture-options">
                <div class="capture-option">
                    <div style="font-size:2rem">📷</div>
                    <strong>ถ่ายภาพด้วยกล้อง</strong>
                    <p>เปิดกล้องหน้าและถ่ายภาพได้จากหน้าเว็บ</p>
                    <button id="open-camera" class="btn" type="button">เปิดกล้อง</button>
                </div>
                <div class="capture-option">
                    <div style="font-size:2rem">🖼️</div>
                    <strong>อัปโหลดรูปภาพ</strong>
                    <p>เลือก JPG, PNG หรือ WebP จากเครื่องหรือโทรศัพท์</p>
                    <button id="choose-file" class="btn secondary" type="button">เลือกรูปภาพ</button>
                </div>
            </div>

            <div id="camera-error" class="notice error camera-error"></div>

            <div id="camera-panel" class="camera-panel">
                <video id="camera-video" class="camera-video" autoplay playsinline muted></video>
                <canvas id="camera-canvas" hidden></canvas>
                <div class="camera-actions">
                    <button id="take-photo" class="btn" type="button">ถ่ายภาพ</button>
                    <button id="close-camera" class="btn secondary" type="button">ปิดกล้อง</button>
                </div>
            </div>

            <div id="preview-panel" class="preview-panel">
                <img id="photo-preview" class="photo-preview" alt="ภาพใบหน้าที่เลือก">
                <div class="preview-actions">
                    <button id="retake-photo" class="btn secondary" type="button">ถ่ายหรือเลือกรูปใหม่</button>
                </div>
            </div>

            <div class="privacy-note">🔒 รูปที่ใช้ค้นหาจะถูกลบจากไฟล์ชั่วคราวหลังประมวลผล ระบบจะแสดงเฉพาะรูปที่ผ่านค่าความเหมือน</div>

            <label class="consent-box">
                <input type="checkbox" name="consent" value="1" required>
                <span>ยินยอมให้ระบบประมวลผลใบหน้าจากภาพนี้เพื่อค้นหารูปในกิจกรรมครั้งนี้</span>
            </label>

            <button id="search-submit" class="btn search-submit" type="submit" disabled>เริ่มค้นหารูปของฉัน</button>
        </form>
    </div>

    <section id="search-status" style="margin-top:24px" aria-live="polite"></section>
    <section id="search-results" class="photo-grid"></section>
</div>

<script>
(() => {
    const fileInput = document.getElementById('selfie');
    const chooseFile = document.getElementById('choose-file');
    const openCamera = document.getElementById('open-camera');
    const closeCamera = document.getElementById('close-camera');
    const takePhoto = document.getElementById('take-photo');
    const retakePhoto = document.getElementById('retake-photo');
    const video = document.getElementById('camera-video');
    const canvas = document.getElementById('camera-canvas');
    const cameraPanel = document.getElementById('camera-panel');
    const previewPanel = document.getElementById('preview-panel');
    const preview = document.getElementById('photo-preview');
    const submit = document.getElementById('search-submit');
    const errorBox = document.getElementById('camera-error');
    let stream = null;
    let previewUrl = '';

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.add('active');
    }

    function clearError() {
        errorBox.textContent = '';
        errorBox.classList.remove('active');
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        video.srcObject = null;
        cameraPanel.classList.remove('active');
    }

    function showPreview(file) {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        previewPanel.classList.add('active');
        submit.disabled = false;
        clearError();
    }

    chooseFile.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;
        stopCamera();
        showPreview(file);
    });

    openCamera.addEventListener('click', async () => {
        clearError();
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError('เบราว์เซอร์นี้ไม่รองรับการเปิดกล้อง กรุณาใช้ปุ่มเลือกรูปภาพแทน');
            return;
        }
        try {
            stopCamera();
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 1280 } },
                audio: false
            });
            video.srcObject = stream;
            cameraPanel.classList.add('active');
            previewPanel.classList.remove('active');
            submit.disabled = true;
        } catch (error) {
            showError('ไม่สามารถเปิดกล้องได้ กรุณาอนุญาตสิทธิ์กล้อง หรือเลือกอัปโหลดรูปภาพแทน');
        }
    });

    takePhoto.addEventListener('click', () => {
        if (!stream || !video.videoWidth) {
            showError('กล้องยังไม่พร้อม กรุณารอสักครู่แล้วลองใหม่');
            return;
        }
        const size = Math.min(video.videoWidth, video.videoHeight);
        const sourceX = (video.videoWidth - size) / 2;
        const sourceY = (video.videoHeight - size) / 2;
        canvas.width = 900;
        canvas.height = 900;
        const context = canvas.getContext('2d');
        context.drawImage(video, sourceX, sourceY, size, size, 0, 0, 900, 900);
        canvas.toBlob(blob => {
            if (!blob) {
                showError('บันทึกภาพจากกล้องไม่สำเร็จ กรุณาลองใหม่');
                return;
            }
            const file = new File([blob], 'camera-selfie.jpg', { type: 'image/jpeg', lastModified: Date.now() });
            const transfer = new DataTransfer();
            transfer.items.add(file);
            fileInput.files = transfer.files;
            stopCamera();
            showPreview(file);
        }, 'image/jpeg', 0.92);
    });

    closeCamera.addEventListener('click', stopCamera);

    retakePhoto.addEventListener('click', () => {
        fileInput.value = '';
        previewPanel.classList.remove('active');
        submit.disabled = true;
        openCamera.focus();
    });

    window.addEventListener('beforeunload', stopCamera);
})();
</script>
<?php page_footer(); ?>