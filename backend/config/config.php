<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_name(): string {
    return (string)($_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Store Administrator');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));

    return strtoupper(
        substr($parts[0] ?? 'A', 0, 1) .
        substr($parts[1] ?? '', 0, 1)
    );
}

$DB_connValues = [
    'host' => 'localhost',
    'dbname' => 'regix_db',
    'username' => 'root',
    'password' => ''
];
