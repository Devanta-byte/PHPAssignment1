<?php
declare(strict_types=1);

function escape(string|null $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submitted) || !is_string($stored) || $stored === '' || !hash_equals($stored, $submitted)) {
        http_response_code(400);
        exit('The form expired or could not be verified. Go back, refresh the page, and try again.');
    }
}

function contact_input(array $source): array
{
    return [
        'first_name' => trim((string) ($source['first_name'] ?? '')),
        'last_name' => trim((string) ($source['last_name'] ?? '')),
        'email' => trim((string) ($source['email'] ?? '')),
        'phone' => trim((string) ($source['phone'] ?? '')),
        'company' => trim((string) ($source['company'] ?? '')),
        'notes' => trim((string) ($source['notes'] ?? '')),
    ];
}

function validate_contact(array $contact): array
{
    $errors = [];

    if ($contact['first_name'] === '' || mb_strlen($contact['first_name']) > 100) {
        $errors['first_name'] = 'Enter a first name with no more than 100 characters.';
    }

    if ($contact['last_name'] === '' || mb_strlen($contact['last_name']) > 100) {
        $errors['last_name'] = 'Enter a last name with no more than 100 characters.';
    }

    if ($contact['email'] !== '' && (!filter_var($contact['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($contact['email']) > 190)) {
        $errors['email'] = 'Enter a valid email address with no more than 190 characters.';
    }

    if (mb_strlen($contact['phone']) > 40) {
        $errors['phone'] = 'Phone number must be 40 characters or fewer.';
    }

    if (mb_strlen($contact['company']) > 150) {
        $errors['company'] = 'Company must be 150 characters or fewer.';
    }

    if (mb_strlen($contact['notes']) > 2000) {
        $errors['notes'] = 'Notes must be 2,000 characters or fewer.';
    }

    return $errors;
}

function validate_contact_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return 'The photo could not be uploaded. Please try again.';
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return 'Choose a photo smaller than 5 MB.';
    }

    $imageInfo = @getimagesize((string) ($file['tmp_name'] ?? ''));
    $mime = $imageInfo['mime'] ?? '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return 'Use a JPEG, PNG, or WebP image.';
    }

    return null;
}

function save_contact_image(array $file): string
{
    $imageInfo = getimagesize((string) $file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$imageInfo['mime']];
    $directory = dirname(__DIR__) . '/uploads/contacts';

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The photo folder could not be created.');
    }

    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The photo could not be saved. Check the uploads folder permissions.');
    }

    return 'uploads/contacts/' . $filename;
}

function delete_contact_image(string|null $imagePath): void
{
    if ($imagePath === null || !preg_match('#^uploads/contacts/[a-f0-9]{32}\.(jpg|png|webp)$#', $imagePath)) {
        return;
    }

    $path = dirname(__DIR__) . '/' . $imagePath;
    if (is_file($path)) {
        unlink($path);
    }
}

function contact_by_id(PDO $db, int $id): array|false
{
    $statement = $db->prepare('SELECT * FROM contacts WHERE id = :id');
    $statement->execute(['id' => $id]);

    return $statement->fetch();
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}
