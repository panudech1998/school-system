<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

function page_header(string $title, bool $admin = false): void
{
    $site = setting('site_title', APP_NAME);
    $user = current_user();
    ?>
<!doctype html>
<html lang="th" data-base-url="<?= e(rtrim(BASE_URL, '/')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> | <?= e($site) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script defer src="<?= e(url('assets/app.js')) ?>"></script>
    <script defer src="<?= e(url('assets/auto-sync.js')) ?>"></script>
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= e(url()) ?>"><?= e($site) ?></a>
    <nav>
        <a href="<?= e(url()) ?>">หน้าหลัก</a>
        <a href="<?= e(url('find.php')) ?>">ค้นหาด้วยใบหน้า</a>
        <?php if ($admin && $user): ?>
            <a href="<?= e(url('admin/')) ?>">หลังบ้าน</a>
            <a href="<?= e(url('logout.php')) ?>">ออกจากระบบ</a>
        <?php else: ?>
            <a href="<?= e(url('login.php')) ?>">ผู้ดูแล</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
<?php foreach (consume_flashes() as $flash): ?>
    <div class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
<?php
}

function page_footer(): void
{
    ?>
</main>
<footer class="site-footer">© <?= date('Y') ?> <?= e(setting('site_title', APP_NAME)) ?></footer>
</body>
</html>
<?php
}
