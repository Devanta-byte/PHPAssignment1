# PHP Assignment 1 — Contact Manager

A small contact manager built with PHP, PDO, and MySQL. It lets you add, view, search, update, and delete contacts, with server-side validation and prepared SQL statements.

## Requirements

- PHP 8.1 or newer with PDO MySQL and the `mbstring` extension
- MySQL 8.0 or MariaDB 10.4 or newer
- XAMPP, Visual Studio Code, and Git

## Run with XAMPP

1. Copy this project folder into XAMPP's `htdocs` directory.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin` and import `database.sql`. This creates the `contact_manager` database and its `contacts` table.
4. If your MySQL credentials differ from the defaults, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in your PHP server environment. The defaults are `127.0.0.1`, `3306`, `contact_manager`, `root`, and an empty password.
5. Visit `http://localhost/PHPAssignment1/`.

You can also create the database by running `database.sql` from the MySQL command line:

```sh
mysql -u root -p < database.sql
```

Remove `-p` if your local MySQL root account has no password.

## Features

- View contacts in alphabetical order, with ten contacts per page
- Search by first name, last name, email, phone number, or company
- Add and edit first name, last name, email, phone, company, and notes
- Delete a contact after confirming the action
- Validate required names, email format, and field lengths on the server
- Use prepared statements, HTML escaping, and CSRF tokens for write actions
- Navigate to email and phone links from the contact list

## Project files

- `index.php` — contact list, search, pagination, and create/edit/delete actions
- `config/database.php` — PDO MySQL connection (supports environment variables)
- `includes/functions.php` — validation, escaping, CSRF, and helper functions
- `database.sql` — database and table setup
- `assets/styles.css` — responsive interface styles

## GitHub

Repository: https://github.com/Devanta-byte/PHPAssignment1

The assignment asks you to send the repository link to your instructor through Microsoft Teams or email.
