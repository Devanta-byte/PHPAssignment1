<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

if (current_user() !== null) {
    redirect('index.php');
}

$db = database();
$errors = [];
$user = registration_input([]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $user = registration_input($_POST);
    $errors = validate_registration($db, $user);

    if ($errors === []) {
        try {
            $statement = $db->prepare(
                'INSERT INTO users (first_name, last_name, email, password_hash)
                 VALUES (:first_name, :last_name, :email, :password_hash)'
            );
            $statement->execute([
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $exception) {
            // 23000 is a duplicate key, in case the same email was registered at the same moment.
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $errors['email'] = 'An account with that email address already exists.';
        }
    }

    if ($errors === []) {
        log_in_user(user_by_email($db, $user['email']));
        set_flash('success', 'Your account was created. Welcome, ' . $user['first_name'] . '!');
        redirect('index.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Create an account for the PHP and MySQL contact manager.">
    <title>Create account · Contact Manager</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<main class="shell">
    <header class="topbar">
        <a class="brand" href="index.php" aria-label="Contact Manager home">
            <span class="brand-mark" aria-hidden="true">C</span>
            <span><strong>Contact Manager</strong><small>Keep your people close</small></span>
        </a>
        <a class="button button-quiet" href="login.php">Log in</a>
    </header>

    <section class="form-panel auth-panel">
        <div class="section-heading"><p class="eyebrow">GET STARTED</p><h1>Create an account</h1></div>
        <p class="intro">Register to start keeping your contacts in one place.</p>

        <?php if ($errors !== []): ?>
            <div class="notice notice-error" role="alert"><strong>Please check these fields:</strong>
                <ul><?php foreach ($errors as $message): ?><li><?= escape($message) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="post" action="register.php" class="contact-form">
            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
            <div class="field-grid">
                <label>First name <span class="required">*</span><input name="first_name" maxlength="100" required autocomplete="given-name" value="<?= escape($user['first_name']) ?>" aria-invalid="<?= isset($errors['first_name']) ? 'true' : 'false' ?>"></label>
                <label>Last name <span class="required">*</span><input name="last_name" maxlength="100" required autocomplete="family-name" value="<?= escape($user['last_name']) ?>" aria-invalid="<?= isset($errors['last_name']) ? 'true' : 'false' ?>"></label>
                <label class="full-width">Email address <span class="required">*</span><input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= escape($user['email']) ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"></label>
                <label>Password <span class="required">*</span><input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password" aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>"><span class="field-hint">At least 8 characters, with a letter and a number</span></label>
                <label>Confirm password <span class="required">*</span><input type="password" name="confirm_password" minlength="8" maxlength="72" required autocomplete="new-password" aria-invalid="<?= isset($errors['confirm_password']) ? 'true' : 'false' ?>"></label>
            </div>
            <div class="form-actions"><a class="button button-quiet" href="login.php">Cancel</a><button class="button button-primary" type="submit">Create account</button></div>
        </form>
        <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
    </section>
</main>
</body>
</html>
