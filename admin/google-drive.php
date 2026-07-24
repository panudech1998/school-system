<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_admin();

$error = '';
$testResult = null;

function count_local_images(string $folder): int
{
    $count = 0;
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        if (in_array(strtolower($file->getExtension()), $allowed, true)) {
            $count++;
        }
    }

    return $count;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $folder = trim((string) ($_POST['folder_path'] ?? ''), " \t\n\r\0\x0B\"'");

        if ($folder === '') {
            throw new RuntimeException('กรุณากรอกตำแหน่งโฟลเดอร์');
        }
        if (!is_dir($folder)) {
            throw new RuntimeException('ไม่พบโฟลเดอร์นี้ในเครื่องเซิร์ฟเวอร์');
        }
        if (!is_readable($folder)) {
            throw new RuntimeException('Apache ไม่มีสิทธิ์อ่านโฟลเดอร์นี้');
        }

        $realPath = realpath($folder) ?: $folder;
        $testResult = [
            'path' => $realPath,
            'images' => count_local_images($realPath),
        ];
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$detectedDrives = [];
if (PHP_OS_FAMILY === 'Windows') {
    foreach (range('C', 'Z') as $letter) {
        $root = $letter . ':\\';
        if (is_dir($root) && is_readable($root)) {
            $detectedDrives[] = $root;
        }
    }
}

page_header('โฟลเดอร์ Google Drive', true);
require __DIR__ . '/_nav.php';
?>
<h1>Google Drive แบบไม่ใช้ Google Cloud</h1>

<?php if ($error): ?>
    <div class="notice error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($testResult): ?>
    <div class="notice success">
        อ่านโฟลเดอร์ได้: <strong><?= e($testResult['path']) ?></strong><br>
        พบรูป JPG, PNG หรือ WEBP จำนวน <strong><?= number_format((int) $testResult['images']) ?></strong> รูป
    </div>
<?php endif; ?>

<div class="form-card">
    <h2>วิธีใช้งานที่ง่ายที่สุด</h2>
    <ol>
        <li>ติดตั้งและลงชื่อเข้าใช้ <strong>Google Drive for desktop</strong> บนเครื่องที่เปิด XAMPP</li>
        <li>ตั้งโฟลเดอร์รูปให้พร้อมใช้งานแบบออฟไลน์ หรือใช้โหมด Mirror files</li>
        <li>คัดลอกตำแหน่งโฟลเดอร์ เช่น <code>G:\My Drive\งานปัจฉิม 2569</code></li>
        <li>นำตำแหน่งนี้ไปใส่ในหน้าจัดการกิจกรรม แล้วกดซิงก์</li>
    </ol>

    <p class="notice">วิธีนี้ไม่ใช้ Client ID, Client Secret, OAuth หรือ Google Cloud ระบบอ่านรูปจากโฟลเดอร์ Drive ที่ซิงก์อยู่ในเครื่องโดยตรง</p>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="field">
            <label for="folder_path">ทดสอบตำแหน่งโฟลเดอร์</label>
            <input id="folder_path" name="folder_path" placeholder="G:\My Drive\งานปัจฉิม 2569" required>
            <small>ตำแหน่งต้องอยู่ในเครื่องเดียวกับ XAMPP และ Apache ต้องอ่านได้</small>
        </div>
        <button class="btn" type="submit">ทดสอบโฟลเดอร์</button>
        <a class="btn secondary" href="<?= e(url('admin/events.php')) ?>">ไปตั้งค่ากิจกรรม</a>
    </form>
</div>

<?php if ($detectedDrives): ?>
<div class="form-card" style="margin-top:20px">
    <h2>ไดรฟ์ที่ระบบมองเห็น</h2>
    <p><?= e(implode(', ', $detectedDrives)) ?></p>
    <small>Google Drive for desktop มักปรากฏเป็นไดรฟ์ เช่น G:\ หรือ H:\</small>
</div>
<?php endif; ?>

<div class="form-card" style="margin-top:20px">
    <h2>ข้อควรทราบ</h2>
    <p>เว็บเบราว์เซอร์ไม่สามารถเลือกโฟลเดอร์บนเครื่องเซิร์ฟเวอร์ให้ PHP โดยตรงได้ เพื่อความปลอดภัย จึงต้องคัดลอกตำแหน่งโฟลเดอร์จาก File Explorer มาวางหนึ่งครั้งต่อกิจกรรม</p>
    <p>เมื่อมีการเพิ่มรูปใน Google Drive for desktop ไฟล์จะซิงก์ลงเครื่อง แล้วกด “ซิงก์” ใน SWK_Phonto เพื่ออัปเดตรูปและดัชนีใบหน้า</p>
</div>
<?php page_footer(); ?>