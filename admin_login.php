<?php
// Not linked on the public site. Open /admin_login.php directly.
require __DIR__ . '/config.php';
if (isset($_GET['logout'])) { unset($_SESSION['admin']); header('Location: admin_login.php'); exit; }
if (is_admin()) { header('Location: admin.php'); exit; }
$first = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? ''); $p = $_POST['password'] ?? '';
    if ($first) {
        if (strlen($u) < 3 || strlen($p) < 8) $err = 'Username needs 3+ characters and password 8+.';
        else { db()->prepare('INSERT INTO admins (username,password_hash) VALUES (?,?)')->execute([$u, password_hash($p, PASSWORD_DEFAULT)]); $first = false; $err = 'Admin created. Sign in below.'; }
    } elseif (($_SESSION['atries'] ?? 0) >= 8) { $err = 'Too many attempts. Try again later.'; }
    else {
        $st = db()->prepare('SELECT * FROM admins WHERE username=?'); $st->execute([$u]); $a = $st->fetch();
        if ($a && password_verify($p, $a['password_hash'])) { session_regenerate_id(true); $_SESSION['admin'] = $a['id']; $_SESSION['atries'] = 0; header('Location: admin.php'); exit; }
        $_SESSION['atries'] = ($_SESSION['atries'] ?? 0) + 1; $err = 'Wrong username or password.';
    }
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Admin sign in</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="app.css"></head><body class="auth dark">
<div class="authbox">
  <div class="brand"><span class="dot"></span>Admin console</div>
  <h1><?= $first ? 'Create the first admin' : 'Admin sign in' ?></h1>
  <?php if ($err): ?><div class="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <label>Username<input name="username" required autofocus></label>
    <label>Password<input name="password" type="password" required></label>
    <button><?= $first ? 'Create admin' : 'Sign in' ?></button>
  </form>
</div></body></html>
