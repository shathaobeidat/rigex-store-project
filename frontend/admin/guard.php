<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../../backend/auth/login.php');
    exit;
}

$statement = DB_conn::getConnection()->prepare(
    "SELECT role
     FROM users
     WHERE id = ? AND deleted_at IS NULL
     LIMIT 1"
);
$statement->execute([(int) $_SESSION['user_id']]);
$user = $statement->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    if ($user && $user['role'] === 'customer') {
        header('Location: ../User/index.php');
        exit;
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cookie['path'],
            $cookie['domain'],
            $cookie['secure'],
            $cookie['httponly']
        );
    }
    session_destroy();
    header('Location: ../../backend/auth/login.php');
    exit;
}
