<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();
$db = database();
$action = (string) ($_GET['action'] ?? 'list');
$allowedActions = ['list', 'create', 'edit', 'view'];
if (!in_array($action, $allowedActions, true)) {
    $action = 'list';
}

$errors = [];
$contact = [
    'first_name' => '', 'last_name' => '', 'email' => '',
    'phone' => '', 'company' => '', 'notes' => '', 'image_path' => null,
];
$contactId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if (in_array($action, ['edit', 'view'], true) && $contactId > 0) {
    $existingContact = contact_by_id($db, $contactId);
    if ($existingContact === false) {
        set_flash('error', 'That contact could not be found.');
        redirect('index.php');
    }
    $contact = $existingContact;
} elseif (in_array($action, ['edit', 'view'], true)) {
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

        $existing = contact_by_id($db, $deleteId);
        $statement = $db->prepare('DELETE FROM contacts WHERE id = :id');
        $statement->execute(['id' => $deleteId]);
        if ($statement->rowCount() > 0 && $existing !== false) {
            delete_contact_image($existing['image_path'] ?? null);
        }
        set_flash($statement->rowCount() > 0 ? 'success' : 'error', $statement->rowCount() > 0 ? 'Contact deleted.' : 'That contact could not be found.');
        redirect('index.php');
    }

    if ($postAction === 'save') {
        $contact = contact_input($_POST);
        $submittedId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $saveAction = $submittedId > 0 ? 'edit' : 'create';
        $contactId = $submittedId;
        $errors = validate_contact($contact);
        $imageError = validate_contact_image($_FILES['photo'] ?? []);
        if ($imageError !== null) {
            $errors['photo'] = $imageError;
        }
        $existingForSave = $saveAction === 'edit' ? contact_by_id($db, $submittedId) : false;
        $contact['image_path'] = $existingForSave !== false ? ($existingForSave['image_path'] ?? null) : null;

        if ($saveAction === 'edit' && ($submittedId < 1 || $existingForSave === false)) {
            set_flash('error', 'That contact could not be found.');
            redirect('index.php');
        }

        if ($errors === []) {
            $uploadedImage = null;
            if (($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $uploadedImage = save_contact_image($_FILES['photo']);
                } catch (RuntimeException $exception) {
                    $errors['photo'] = $exception->getMessage();
                }
            }
        }

        if ($errors === []) {
            $values = $contact;
            if ($uploadedImage !== null) {
                $values['image_path'] = $uploadedImage;
            }
            if ($saveAction === 'edit') {
                $values['id'] = $submittedId;
                $statement = $db->prepare(
                    'UPDATE contacts SET first_name = :first_name, last_name = :last_name, email = :email,
                     phone = :phone, company = :company, notes = :notes, image_path = :image_path WHERE id = :id'
                );
                $statement->execute($values);
                if ($uploadedImage !== null) {
                    delete_contact_image($existingForSave['image_path'] ?? null);
                }
                set_flash('success', 'Contact updated.');
            } else {
                $statement = $db->prepare(
                    'INSERT INTO contacts (first_name, last_name, email, phone, company, notes, image_path)
                     VALUES (:first_name, :last_name, :email, :phone, :company, :notes, :image_path)'
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
$sort = (string) ($_GET['sort'] ?? 'name');
$sortOptions = [
    'name' => 'last_name ASC, first_name ASC, id ASC',
    'company' => 'company ASC, last_name ASC, first_name ASC, id ASC',
    'updated' => 'updated_at DESC, last_name ASC, first_name ASC, id ASC',
];
if (!isset($sortOptions[$sort])) {
    $sort = 'name';
}
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 10;
$totalStatement = $db->prepare(
    'SELECT COUNT(*) FROM contacts
     WHERE (CHAR_LENGTH(:query) = 0 OR first_name LIKE :first_name OR last_name LIKE :last_name
        OR email LIKE :email OR phone LIKE :phone OR company LIKE :company OR notes LIKE :notes)'
);
$searchTerm = '%' . $query . '%';
$searchParams = [
    'query' => $query,
    'first_name' => $searchTerm,
    'last_name' => $searchTerm,
    'email' => $searchTerm,
    'phone' => $searchTerm,
    'company' => $searchTerm,
    'notes' => $searchTerm,
];
$totalStatement->execute($searchParams);
$totalContacts = (int) $totalStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalContacts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $db->prepare(
    'SELECT id, first_name, last_name, email, phone, company, image_path FROM contacts
     WHERE (CHAR_LENGTH(:query) = 0 OR first_name LIKE :first_name OR last_name LIKE :last_name
        OR email LIKE :email OR phone LIKE :phone OR company LIKE :company OR notes LIKE :notes)
     ORDER BY ' . $sortOptions[$sort] . ' LIMIT :limit OFFSET :offset'
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
    <title><?= $action === 'list' ? 'Contacts' : ($action === 'edit' ? 'Edit contact' : ($action === 'view' ? 'Contact details' : 'Add contact')) ?> · Contact Manager</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<main class="shell">
    <header class="topbar">
        <a class="brand" href="index.php" aria-label="Contact Manager home">
            <span class="brand-mark" aria-hidden="true">C</span>
            <span><strong>Contact Manager</strong><small>Keep your people close</small></span>
        </a>
        <div class="topbar-actions">
            <span class="signed-in">Signed in as <strong><?= escape($user['first_name'] . ' ' . $user['last_name']) ?></strong></span>
            <a class="button button-primary" href="index.php?action=create"><span aria-hidden="true">＋</span> Add contact</a>
            <form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>"><button class="button button-quiet" type="submit">Log out</button></form>
        </div>
    </header>

    <?php if ($flash !== null): ?>
        <div class="notice notice-<?= escape($flash['type']) ?>" role="status"><?= escape($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($action === 'view'): ?>
        <section class="form-panel detail-panel">
            <a class="back-link" href="index.php">← Back to contacts</a>
            <div class="section-heading"><p class="eyebrow">CONTACT DETAILS</p><h1><?= escape($contact['first_name'] . ' ' . $contact['last_name']) ?></h1></div>
            <?php if (!empty($contact['image_path'])): ?><img class="detail-photo" src="<?= escape($contact['image_path']) ?>" alt="Photo of <?= escape($contact['first_name'] . ' ' . $contact['last_name']) ?>"><?php endif; ?>
            <?php if ($contact['company'] !== ''): ?><p class="intro"><?= escape($contact['company']) ?></p><?php endif; ?>
            <dl class="detail-grid">
                <div><dt>Email address</dt><dd><?= $contact['email'] !== '' ? '<a href="mailto:' . escape($contact['email']) . '">' . escape($contact['email']) . '</a>' : '<span class="muted">Not provided</span>' ?></dd></div>
                <div><dt>Phone number</dt><dd><?= $contact['phone'] !== '' ? '<a href="tel:' . escape($contact['phone']) . '">' . escape($contact['phone']) . '</a>' : '<span class="muted">Not provided</span>' ?></dd></div>
                <div class="detail-notes"><dt>Notes</dt><dd><?= $contact['notes'] !== '' ? nl2br(escape($contact['notes'])) : '<span class="muted">No notes added.</span>' ?></dd></div>
                <div><dt>Created</dt><dd><?= escape(date('M j, Y g:i a', strtotime($contact['created_at']))) ?></dd></div>
                <div><dt>Last updated</dt><dd><?= escape(date('M j, Y g:i a', strtotime($contact['updated_at']))) ?></dd></div>
            </dl>
            <div class="form-actions"><a class="button button-quiet" href="index.php">Back to contacts</a><a class="button button-primary" href="index.php?action=edit&amp;id=<?= (int) $contactId ?>">Edit contact</a></div>
        </section>
    <?php elseif ($action !== 'list'): ?>
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

            <form method="post" action="index.php" class="contact-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= (int) $contactId ?>"><?php endif; ?>
                <div class="field-grid">
                    <label>First name <span class="required">*</span><input name="first_name" maxlength="100" required autocomplete="given-name" value="<?= escape($contact['first_name']) ?>" aria-invalid="<?= isset($errors['first_name']) ? 'true' : 'false' ?>"></label>
                    <label>Last name <span class="required">*</span><input name="last_name" maxlength="100" required autocomplete="family-name" value="<?= escape($contact['last_name']) ?>" aria-invalid="<?= isset($errors['last_name']) ? 'true' : 'false' ?>"></label>
                    <label>Email address<input type="email" name="email" maxlength="190" autocomplete="email" value="<?= escape($contact['email']) ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"></label>
                    <label>Phone number<input type="tel" name="phone" maxlength="40" autocomplete="tel" value="<?= escape($contact['phone']) ?>" aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>"></label>
                    <label class="full-width">Company<input name="company" maxlength="150" autocomplete="organization" value="<?= escape($contact['company']) ?>" aria-invalid="<?= isset($errors['company']) ? 'true' : 'false' ?>"></label>
                    <label class="full-width">Profile photo <span class="field-hint">JPEG, PNG, or WebP · up to 5 MB</span><input class="photo-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp" aria-invalid="<?= isset($errors['photo']) ? 'true' : 'false' ?>"><?php if (!empty($contact['image_path'])): ?><span class="current-photo"><img src="<?= escape($contact['image_path']) ?>" alt="Current contact photo"><span>Current photo — upload a new image to replace it.</span></span><?php endif; ?><?php if (isset($errors['photo'])): ?><span class="field-error"><?= escape($errors['photo']) ?></span><?php endif; ?></label>
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
                    <input type="hidden" name="sort" value="<?= escape($sort) ?>">
                    <label class="visually-hidden" for="contact-search">Search contacts</label>
                    <span aria-hidden="true">⌕</span><input id="contact-search" type="search" name="q" placeholder="Search people..." value="<?= escape($query) ?>">
                    <?php if ($query !== ''): ?><a class="clear-search" href="index.php" aria-label="Clear search">×</a><?php endif; ?>
                    <button type="submit">Search</button>
                </form>
            </div>
            <form method="get" action="index.php" class="sort-form">
                <?php if ($query !== ''): ?><input type="hidden" name="q" value="<?= escape($query) ?>"><?php endif; ?>
                <label for="contact-sort">Sort by</label>
                <select id="contact-sort" name="sort" onchange="this.form.submit()">
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Last name</option>
                    <option value="company" <?= $sort === 'company' ? 'selected' : '' ?>>Company</option>
                    <option value="updated" <?= $sort === 'updated' ? 'selected' : '' ?>>Recently updated</option>
                </select>
            </form>

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
                            <td><div class="person"><?php if (!empty($row['image_path'])): ?><img class="avatar avatar-photo" src="<?= escape($row['image_path']) ?>" alt=""><?php else: ?><span class="avatar" aria-hidden="true"><?= escape(mb_strtoupper(mb_substr($row['first_name'], 0, 1) . mb_substr($row['last_name'], 0, 1))) ?></span><?php endif; ?><span><strong><?= escape($row['first_name'] . ' ' . $row['last_name']) ?></strong><small>Contact</small></span></div></td>
                            <td><?php if ($row['email'] !== ''): ?><a href="mailto:<?= escape($row['email']) ?>"><?= escape($row['email']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                            <td><?php if ($row['phone'] !== ''): ?><a href="tel:<?= escape($row['phone']) ?>"><?= escape($row['phone']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                            <td><?= $row['company'] !== '' ? escape($row['company']) : '<span class="muted">—</span>' ?></td>
                            <td><div class="row-actions"><a class="icon-button" href="index.php?action=view&amp;id=<?= (int) $row['id'] ?>" aria-label="View <?= escape($row['first_name'] . ' ' . $row['last_name']) ?>" title="View contact">↗</a><a class="icon-button" href="index.php?action=edit&amp;id=<?= (int) $row['id'] ?>" aria-label="Edit <?= escape($row['first_name'] . ' ' . $row['last_name']) ?>" title="Edit contact">✎</a><form method="post" action="index.php" onsubmit="return confirm('Delete this contact? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="icon-button delete-button" type="submit" aria-label="Delete <?= escape($row['first_name'] . ' ' . $row['last_name']) ?>" title="Delete contact">⌫</button></form></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <div class="list-footer"><span>Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalContacts) ?> of <?= $totalContacts ?> contacts</span>
                    <?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Contact pages"><?php if ($page > 1): ?><a href="?q=<?= rawurlencode($query) ?>&amp;sort=<?= rawurlencode($sort) ?>&amp;page=<?= $page - 1 ?>" aria-label="Previous page">←</a><?php endif; ?><span>Page <?= $page ?> of <?= $totalPages ?></span><?php if ($page < $totalPages): ?><a href="?q=<?= rawurlencode($query) ?>&amp;sort=<?= rawurlencode($sort) ?>&amp;page=<?= $page + 1 ?>" aria-label="Next page">→</a><?php endif; ?></nav><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <footer class="page-footer">A little more organized, one contact at a time.</footer>
    <?php endif; ?>
</main>
</body>
</html>
