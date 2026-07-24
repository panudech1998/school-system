<?php

declare(strict_types=1);
?>
<nav class="admin-nav">
    <a class="btn" href="<?= e(url('admin/')) ?>">Dashboard</a>
    <a class="btn secondary" href="<?= e(url('admin/events.php')) ?>">กิจกรรม</a>
    <a class="btn secondary" href="<?= e(url('admin/google-drive.php')) ?>">Google Drive</a>
    <a class="btn secondary" href="<?= e(url('admin/settings.php')) ?>">ตั้งค่าเว็บไซต์</a>
</nav>
