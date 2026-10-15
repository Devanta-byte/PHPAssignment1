# PHP Assignment 1 — Contact Manager

A small contact manager built with PHP, PDO, and MySQL. Users register for an account and log in, then add, view, search, update, and delete contacts, with server-side validation and prepared SQL statements.

## Requirements

- PHP 8.1 or newer with PDO MySQL and the `mbstring` extension
- MySQL 8.0 or MariaDB 10.4 or newer
- XAMPP, Visual Studio Code, and Git

## Run with XAMPP

1. Copy this project folder into XAMPP's `htdocs` directory.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin` and import `database.sql`. This creates the `contact_manager` database with its `users` and `contacts` tables.
   If you already imported the database for an earlier assignment, run these once in phpMyAdmin instead of re-importing (skip the `ALTER TABLE` if you already added the photo column for Assignment 3):

   ```sql
   ALTER TABLE contacts ADD COLUMN image_path VARCHAR(255) NULL DEFAULT NULL;

   CREATE TABLE IF NOT EXISTS users (
       id INT UNSIGNED NOT NULL AUTO_INCREMENT,
       first_name VARCHAR(100) NOT NULL,
       last_name VARCHAR(100) NOT NULL,
       email VARCHAR(190) NOT NULL,
       password_hash VARCHAR(255) NOT NULL,
       created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (id),
       UNIQUE KEY uq_users_email (email)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```
4. If your MySQL credentials differ from the defaults, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in your PHP server environment. The defaults are `127.0.0.1`, `3306`, `contact_manager`, `root`, and an empty password.
5. Visit `http://localhost/PHPAssignment1/`. You'll be sent to the login page — choose **Create account** to register, and you'll be logged in automatically.

You can also create the database by running `database.sql` from the MySQL command line:

```sh
mysql -u root -p < database.sql
```

Remove `-p` if your local MySQL root account has no password.

## Features

- Register an account with first name, last name, email, and password (minimum 8 characters, with a letter and a number)
- Log in with email and password; passwords are stored with `password_hash()` and checked with `password_verify()`
- Contact pages require a logged-in user; log out from the top bar
- View contacts in alphabetical order, with ten contacts per page
- Sort contacts by last name, company, or recently updated
- Search by first name, last name, email, phone number, company, or notes
- Open a contact details page to review notes and created/updated timestamps
- Upload a JPEG, PNG, or WebP profile photo (up to 5 MB); replace it while editing or remove it with the contact
- Add and edit first name, last name, email, phone, company, and notes
- Delete a contact after confirming the action
- Validate required names, email format, and field lengths on the server
- Use prepared statements, HTML escaping, and CSRF tokens for write actions
- Navigate to email and phone links from the contact list

## Project files

- `index.php` — contact list, search, pagination, and create/edit/delete actions (login required)
- `register.php` — new account registration
- `login.php` / `logout.php` — sign in and sign out
- `config/database.php` — PDO MySQL connection (supports environment variables)
- `includes/functions.php` — validation, escaping, CSRF, authentication, and helper functions
- `database.sql` — database, `users` table, and `contacts` table setup
- `assets/styles.css` — responsive interface styles
- `uploads/contacts/` — saved profile photos (excluded from Git)

## GitHub

Repository: https://github.com/Devanta-byte/PHPAssignment1

The assignment asks you to send the repository link to your instructor through Microsoft Teams or email.
