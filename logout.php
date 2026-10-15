<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verify_csrf_token();
$_SESSION = [];
session_regenerate_id(true);
set_flash('success', 'You have been logged out.');
redirect('login.php');
