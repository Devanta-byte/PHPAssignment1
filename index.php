<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

$db = database();
$action = (string) ($_GET['action'] ?? 'list');
$allowedActions = ['list', 'create', 'edit'];
if (!in_array($action, $allowedActions, true)) {
    $action = 'list';
}

$errors = [];
$contact = [
    'first_name' => '', 'last_name' => '', 'email' => '',
    'phone' => '', 'company' => '', 'notes' => '',
];
$contactId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if (($action === 'edit') && $contactId > 0) {
    $existingContact = contact_by_id($db, $contactId);
    if ($existingContact === false) {
        set_flash('error', 'That contact could not be found.');
        redirect('index.php');
    }
    $contact = $existingContact;
} elseif ($action === 'edit') {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $postAction = (string) ($_POST['action'] ?? '');

    if ($postAction === 'delete') {
        $deleteId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$deleteId || $deleteId < 1) {
            set_flash('error', 'The contact could not be deleted.');
            redirect('index.php');
        }

        $statement = $db->prepare('DELETE FROM contacts WHERE id = :id');
        $statement->execute(['id' => $deleteId]);
        set_flash($statement->rowCount() > 0 ? 'success' : 'error', $statement->rowCount() > 0 ? 'Contact deleted.' : 'That contact could not be found.');
        redirect('index.php');
    }

    if ($postAction === 'save') {
        $contact = contact_input($_POST);
        $submittedId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $saveAction = $submittedId > 0 ? 'edit' : 'create';
        $contactId = $submittedId;
        $errors = validate_contact($contact);

        if ($saveAction === 'edit' && ($submittedId < 1 || contact_by_id($db, $submittedId) === false)) {
            set_flash('error', 'That contact could not be found.');
            redirect('index.php');
        }

        if ($errors === []) {
            $values = $contact;
            if ($saveAction === 'edit') {
                $values['id'] = $submittedId;
                $statement = $db->prepare(
                    'UPDATE contacts SET first_name = :first_name, last_name = :last_name, email = :email,
                     phone = :phone, company = :company, notes = :notes WHERE id = :id'
                );
                $statement->execute($values);
                set_flash('success', 'Contact updated.');
            } else {
                $statement = $db->prepare(
                    'INSERT INTO contacts (first_name, last_name, email, phone, company, notes)
                     VALUES (:first_name, :last_name, :email, :phone, :company, :notes)'
                );
                $statement->execute($values);
                set_flash('success', 'Contact added.');
            }
            redirect('index.php');
        }

        $action = $saveAction;
    }
}

$query = trim((string) ($_GET['q'] ?? ''));
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 10;
$totalStatement = $db->prepare(
    'SELECT COUNT(*) FROM contacts
     WHERE (CHAR_LENGTH(:query) = 0 OR first_name LIKE :first_name OR last_name LIKE :last_name
        OR email LIKE :email OR phone LIKE :phone OR company LIKE :company)'
);
$searchTerm = '%' . $query . '%';
$searchParams = [
    'query' => $query,
    'first_name' => $searchTerm,
    'last_name' => $searchTerm,
    'email' => $searchTerm,
    'phone' => $searchTerm,
    'company' => $searchTerm,
];
$totalStatement->execute($searchParams);
$totalContacts = (int) $totalStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalContacts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $db->prepare(
    'SELECT id, first_name, last_name, email, phone, company FROM contacts
     WHERE (CHAR_LENGTH(:query) = 0 OR first_name LIKE :first_name OR last_name LIKE :last_name
        OR email LIKE :email OR phone LIKE :phone OR company LIKE :company)
     ORDER BY last_name ASC, first_name ASC, id ASC LIMIT :limit OFFSET :offset'
);
foreach ($searchParams as $key => $value) {
    $listStatement->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$contacts = $listStatement->fetchAll();
$flash = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A simple PHP and MySQL contact manager.">
    <title><?= $action === 'list' ? 'Contacts' : ($action === 'edit' ? 'Edit contact' : 'Add contact') ?> · Contact Manager</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<main class="shell">
    <header class="topbar">
        <a class="brand" href="index.php" aria-label="Contact Manager home">
            <span class="brand-mark" aria-hidden="true">C</span>
            <span><strong>Contact Manager</strong><small>Keep your people close</small></span>
        </a>
        <a class="button button-primary" href="index.php?action=create"><span aria-hidden="true">＋</span> Add contact</a>
    </header>

    <?php if ($flash !== null): ?>
        <div class="notice notice-<?= escape($flash['type']) ?>" role="status"><?= escape($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($action !== 'list'): ?>
        <section class="form-panel">
            <a class="back-link" href="index.php">← Back to contacts</a>
            <div class="section-heading">
                <div><p class="eyebrow">YOUR ADDRESS BOOK</p><h1><?= $action === 'edit' ? 'Edit contact' : 'Add a contact' ?></h1></div>
            </div>
            <p class="intro">Save the details you need to stay in touch.</p>

            <?php if ($errors !== []): ?>
                <div class="notice notice-error" role="alert"><strong>Please check these fields:</strong>
                    <ul><?php foreach ($errors as $message): ?><li><?= escape($message) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php" class="contact-form">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= (int) $contactId ?>"><?php endif; ?>
                <div class="field-grid">
                    <label>First name <span class="required">*</span><input name="first_name" maxlength="100" required autocomplete="given-name" value="<?= escape($contact['first_name']) ?>" aria-invalid="<?= isset($errors['first_name']) ? 'true' : 'false' ?>"></label>
                    <label>Last name <span class="required">*</span><input name="last_name" maxlength="100" required autocomplete="family-name" value="<?= escape($contact['last_name']) ?>" aria-invalid="<?= isset($errors['last_name']) ? 'true' : 'false' ?>"></label>
                    <label>Email address<input type="email" name="email" maxlength="190" autocomplete="email" value="<?= escape($contact['email']) ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"></label>
                    <label>Phone number<input type="tel" name="phone" maxlength="40" autocomplete="tel" value="<?= escape($contact['phone']) ?>" aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>"></label>
                    <label class="full-width">Company<input name="company" maxlength="150" autocomplete="organization" value="<?= escape($contact['company']) ?>" aria-invalid="<?= isset($errors['company']) ? 'true' : 'false' ?>"></label>
                    <label class="full-width">Notes<textarea name="notes" maxlength="2000" rows="4" placeholder="Add a detail you want to remember"><?= escape($contact['notes']) ?></textarea><span class="field-hint">Up to 2,000 characters</span></label>
                </div>
                <div class="form-actions"><a class="button button-quiet" href="index.php">Cancel</a><button class="button button-primary" type="submit"><?= $action === 'edit' ? 'Save changes' : 'Save contact' ?></button></div>
            </form>
        </section>
    <?php else: ?>
        <section class="hero">
            <div><p class="eyebrow">YOUR ADDRESS BOOK</p><h1>People worth keeping in touch with.</h1><p>All your important contact details, in one easy place.</p></div>
            <div class="hero-orbit" aria-hidden="true"><span>✳</span></div>
        </section>

        <section class="list-panel" aria-labelledby="contacts-title">
            <div class="list-heading">
                <div><p class="eyebrow">THE PEOPLE YOU KNOW</p><h2 id="contacts-title">Your contacts <span class="count"><?= $totalContacts ?></span></h2></div>
                <form method="get" action="index.php" class="search-form" role="search">
                    <label class="visually-hidden" for="contact-search">Search contacts</label>
                    <span aria-hidden="true">⌕</span><input id="contact-search" type="search" name="q" placeholder="Search people..." value="<?= escape($query) ?>">
                    <?php if ($query !== ''): ?><a class="clear-search" href="index.php" aria-label="Clear search">×</a><?php endif; ?>
                    <button type="submit">Search</button>
                </form>
            </div>

            <?php if ($contacts === []): ?>
                <div class="empty-state">
                    <div class="empty-icon" aria-hidden="true"><?= $query !== '' ? '⌕' : '♡' ?></div>
                    <h3><?= $query !== '' ? 'No contacts found' : 'Your contact list is ready' ?></h3>
                    <p><?= $query !== '' ? 'Try a different name, email, phone number, or company.' : 'Add your first contact to get started.' ?></p>
                    <?php if ($query === ''): ?><a class="button button-primary" href="index.php?action=create">Add your first contact</a><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Phone</th><th scope="col">Company</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($contacts as $row): ?>
                        <tr>
                            <td><div class="person"><span class="avatar" aria-hidden="true"><?= escape(mb_strtoupper(mb_substr($row['first_name'], 0, 1) . mb_substr($row['last_name'], 0, 1))) ?></span><span><strong><?= escape($row['first_name'] . ' ' . $row['last_name']) ?></strong><small>Contact</small></span></div></td>
                            <td><?php if ($row['email'] !== ''): ?><a href="mailto:<?= escape($row['email']) ?>"><?= escape($row['email']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                            <td><?php if ($row['phone'] !== ''): ?><a href="tel:<?= escape($row['phone']) ?>"><?= escape($row['phone']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                            <td><?= $row['company'] !== '' ? escape($row['company']) : '<span class="muted">—</span>' ?></td>
                            <td><div class="row-actions"><a class="icon-button" href="index.php?action=edit&amp;id=<?= (int) $row['id'] ?>" aria-label="Edit <?= escape($row['first_name'] . ' ' . $row['last_name']) ?>" title="Edit contact">✎</a><form method="post" action="index.php" onsubmit="return confirm('Delete this contact? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="icon-button delete-button" type="submit" aria-label="Delete <?= escape($row['first_name'] . ' ' . $row['last_name']) ?>" title="Delete contact">⌫</button></form></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <div class="list-footer"><span>Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalContacts) ?> of <?= $totalContacts ?> contacts</span>
                    <?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Contact pages"><?php if ($page > 1): ?><a href="?q=<?= rawurlencode($query) ?>&amp;page=<?= $page - 1 ?>" aria-label="Previous page">←</a><?php endif; ?><span>Page <?= $page ?> of <?= $totalPages ?></span><?php if ($page < $totalPages): ?><a href="?q=<?= rawurlencode($query) ?>&amp;page=<?= $page + 1 ?>" aria-label="Next page">→</a><?php endif; ?></nav><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <footer class="page-footer">A little more organized, one contact at a time.</footer>
    <?php endif; ?>
</main>
</body>
</html>
