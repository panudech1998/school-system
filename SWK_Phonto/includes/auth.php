<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function admin_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_admin(): array
{
    $user = admin_user();
    if (!$user) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? url('admin/');
        redirect('login.php');
    }
    return $user;
}
