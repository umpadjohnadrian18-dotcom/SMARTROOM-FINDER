<?php
require __DIR__ . '/config.php';
unset($_SESSION['user']);
header('Location: login.php'); exit;
