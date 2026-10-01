<?php
require __DIR__ . '/config.php';
if (me()) { header('Location: index.php'); exit; }
$err = ''; $mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = ($_POST['mode'] ?? '') === 'register' ? 'register' : 'login';
    $no = strtoupper(trim($_POST['student_no'] ?? '')); $pw = $_POST['password'] ?? '';
    if ($mode === 'register') {
        $name = trim($_POST['full_name'] ?? '');
        if (!preg_match('/^[A-Z0-9-]{4,20}$/', $no)) $err = 'Student number must be 4-20 letters, numbers or dashes.';
        elseif (strlen($name) < 3) $err = 'Please enter your full name.';
        elseif (strlen($pw) < 8) $err = 'Password needs at least 8 characters.';
        else {
            $x = db()->prepare('SELECT 1 FROM users WHERE student_no=?'); $x->execute([$no]);
            if ($x->fetch()) $err = 'That student number is already registered.';
            else {
                db()->prepare('INSERT INTO users (student_no,full_name,password_hash) VALUES (?,?,?)')->execute([$no, mb_substr($name, 0, 80), password_hash($pw, PASSWORD_DEFAULT)]);
                $_SESSION['user'] = (int)db()->lastInsertId(); session_regenerate_id(true);
                header('Location: index.php'); exit;
            }
        }
    } else {
        $_SESSION['tries'] = ($_SESSION['tries'] ?? 0);
        if ($_SESSION['tries'] >= 8) $err = 'Too many attempts. Close the browser and try again later.';
        else {
            $st = db()->prepare('SELECT * FROM users WHERE student_no=?'); $st->execute([$no]); $u = $st->fetch();
            if ($u && password_verify($pw, $u['password_hash'])) {
                session_regenerate_id(true); $_SESSION['user'] = (int)$u['id']; $_SESSION['tries'] = 0;
                header('Location: index.php'); exit;
            }
            $_SESSION['tries']++; $err = 'Wrong student number or password.';
        }
    }
}
$reg = $mode === 'register';
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in · Smart Room Finder</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="app.css">
</head><body class="auth">
<div class="authbox">
  <div class="brand"><span class="dot"></span>Smart Room Finder</div>
  <h1><?= $reg ? 'Create your student account' : 'Sign in to book a room' ?></h1>
  <p class="note"><?= $reg ? 'Use your student number so your requests can be tracked.' : 'Find open rooms, request one, and see if it was approved.' ?></p>
  <?php if ($err): ?><div class="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
    <input type="hidden" name="mode" value="<?= $mode ?>">
    <?php if ($reg): ?><label>Full name<input name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>"></label><?php endif; ?>
    <label>Student number<input name="student_no" required value="<?= e($_POST['student_no'] ?? '') ?>" <?= $reg ? '' : 'autofocus' ?>></label>
    <label>Password<input name="password" type="password" required minlength="<?= $reg ? 8 : 1 ?>"></label>
    <button><?= $reg ? 'Create account' : 'Sign in' ?></button>
  </form>
  <p class="note"><?= $reg ? 'Already registered? <a href="login.php">Sign in</a>' : 'New here? <a href="login.php?mode=register">Create an account</a>' ?></p>
</div></body></html>
