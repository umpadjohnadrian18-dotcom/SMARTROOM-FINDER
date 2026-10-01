<?php
// ---- EDIT THESE with the values from your AwardSpace control panel (MySQL section) ----
const DB_HOST = 'fdb1034.awardspace.net'; // use the "Database host" shown in AwardSpace
const DB_NAME = '4669738_john';
const DB_USER = '4669738_john';
const DB_PASS = 'Adrian123';
// ---------------------------------------------------------------------------------------

session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
session_start();
date_default_timezone_set('Asia/Manila');

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function is_admin(): bool { return !empty($_SESSION['admin']); }
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function is_user(): bool { return !empty($_SESSION['user']); }
function me(): ?array {
    if (!is_user()) return null;
    $st = db()->prepare('SELECT id,student_no,full_name FROM users WHERE id=?'); $st->execute([$_SESSION['user']]);
    return $st->fetch() ?: null;
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
