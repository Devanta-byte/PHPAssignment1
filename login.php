<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

if (current_user() !== null) {
    redirect('index.php');
}

$db = database();
$email = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $user = $email !== '' ? user_by_email($db, $email) : false;
    if ($user !== false && password_verify($password, $user['password_hash'])) {
        log_in_user($user);
        set_flash('success', 'Welcome back, ' . $user['first_name'] . '.');
        redirect('index.php');
    }

    $error = 'The email address or password is incorrect.';
}

$flash = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Log in to the PHP and MySQL contact manager.">
    <title>Log in · Contact Manager</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<main class="shell">
    <header class="topbar">
        <a class="brand" href="index.php" aria-label="Contact Manager home">
            <span class="brand-mark" aria-hidden="true">C</span>
            <span><strong>Contact Manager</strong><small>Keep your people close</small></span>
        </a>
        <a class="button button-quiet" href="register.php">Create account</a>
    </header>

    <section class="form-panel auth-panel">
        <div class="section-heading"><p class="eyebrow">WELCOME BACK</p><h1>Log in</h1></div>
        <p class="intro">Log in to view and manage your contacts.</p>

        <?php if ($flash !== null): ?>
            <div class="notice notice-<?= escape($flash['type']) ?>" role="status"><?= escape($flash['message']) ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="notice notice-error" role="alert"><?= escape($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" class="contact-form">
            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
            <div class="field-grid">
                <label class="full-width">Email address <span class="required">*</span><input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= escape($email) ?>" aria-invalid="<?= $error !== null ? 'true' : 'false' ?>"></label>
                <label class="full-width">Password <span class="required">*</span><input type="password" name="password" required autocomplete="current-password" aria-invalid="<?= $error !== null ? 'true' : 'false' ?>"></label>
            </div>
            <div class="form-actions"><button class="button button-primary" type="submit">Log in</button></div>
        </form>
        <p class="auth-switch">Don't have an account? <a href="register.php">Create one</a></p>
    </section>
</main>
</body>
</html>
