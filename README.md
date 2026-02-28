# LoveJoy Jewellery Evaluation - Secure Web Application

This is a secure PHP and MySQL web application built for a Computer Security coursework.

Customers can:
- Register and log in
- Submit jewellery evaluation requests, including an optional photo of the item
- Choose their preferred contact method (email or phone)

Administrators can:
- View a list of all submitted evaluation requests

The main focus of this project is secure web development: authentication, password policy, secure file upload, and protection against common web vulnerabilities.

---

## Tech Stack

- Backend: PHP (tested with XAMPP and Apache)
- Database: MySQL or MariaDB
- Libraries (installed via Composer):
  - PHPMailer (SMTP email for password reset)
  - PragmaRX Google2FA (Google Authenticator 2FA)
- External services:
  - Google reCAPTCHA v2 (checkbox)

---

## Main Features

- User registration:
  - Email, password, full name, phone number
  - Security question and answer for password recovery

- Secure login:
  - Password hashing using password_hash
  - Account lockout after multiple failed attempts
  - Optional Google Authenticator 2FA
  - Google reCAPTCHA on the login form

- Password recovery:
  - Time limited reset tokens stored in the database
  - Password reset link sent via email using PHPMailer and Gmail SMTP
  - Security question and answer required to complete the reset
  - Password strength validation on reset

- Evaluation request page:
  - Only accessible to logged in users
  - Text description of the item and what the user wants evaluated
  - Dropdown for preferred contact method (email or phone)
  - Optional image upload for a photo of the item

- Admin only evaluations list:
  - Only accessible to users with role "admin"
  - Shows basic customer details, description, contact method, and link to uploaded image

---

## Security Features

### Password security

- Passwords are never stored in plain text.
- Passwords are hashed on the server using PHP password_hash.
- Security answers for security questions are also stored as hashes.
- Password strength checks on the server:
  - Minimum length
  - Uppercase letters
  - Lowercase letters
  - Digits
  - Special characters
- Account lockout:
  - The application tracks failed login attempts.
  - After a configurable number of failures, the account is locked until a future time.

### SQL injection protection

- All database access is done through PDO with prepared statements.
- User input is never directly concatenated into SQL queries.

Example pattern used in the code:

    $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);

### Cross site scripting (XSS) mitigation

- Any user controlled data that is displayed in HTML is escaped using htmlspecialchars.
- This prevents HTML and JavaScript from being executed when viewing user input.

Example pattern:

    <?= htmlspecialchars($requestRow['description']) ?>

### CSRF (Cross Site Request Forgery) protection

- State changing forms (register, login, evaluation request, forgot password, reset password) use CSRF tokens.
- Each form:
  - Generates a random token on GET and stores it in the session.
  - Includes the token as a hidden input field.
  - Verifies on POST that the submitted token matches the stored token.

Example hidden field:

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

Example server side check:

    $postedToken = $_POST['csrf_token'] ?? '';
    if (empty($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        $errors[] = "Invalid form submission. Please try again.";
    }

### File upload hardening

- The evaluation request page allows uploading an image file.
- Security checks include:
  - Rejecting files larger than a configured maximum size (for example 2 MB).
  - Restricting file extensions to a safe list (jpg, jpeg, png, gif).
  - Using a unique generated filename to avoid collisions.
  - Storing the file path in the database, not arbitrary paths from the user.

Example checks:

    $maximumFileSize = 2 * 1024 * 1024; // 2 MB
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

### Authentication enhancements

- Google reCAPTCHA v2 on the login form to slow down bots and automated brute force attempts.
- Two factor authentication using Google Authenticator:
  - Each user can enable 2FA.
  - A secret key is generated and stored in the database.
  - A QR code is shown for scanning in the Authenticator app.
  - On login, if 2FA is enabled, the user must also input a 6 digit one time code.

---

## Database Overview

Main tables:

### users

Typical columns:

- id
- email
- password_hash
- name
- phone
- role
- security_question
- security_answer_hash
- failed_attempts
- locked_until
- twofa_secret
- twofa_enabled

### evaluation_requests

Typical columns:

- id
- user_id
- description
- preferred_contact
- photo_path
- created_at

### password_resets

Typical columns:

- id
- user_id
- token
- expires_at

Foreign keys:

- evaluation_requests.user_id references users.id
- password_resets.user_id references users.id

---

## How To Run The Project Locally

### 1. Prerequisites

You will need:

- XAMPP or a similar PHP and MySQL environment.
- Composer installed on your system.
- A Google reCAPTCHA v2 site key and secret key.
- A Gmail account and app password if you want email based password reset to work.

### 2. Clone the repository

    git clone https://github.com/<your-username>/<your-repo-name>.git
    cd <your-repo-name>

Replace `<your-username>` and `<your-repo-name>` with your GitHub details.

### 3. Install Composer dependencies

From the project root:

    composer install

This will create the vendor directory and install PHPMailer, Google2FA, and other PHP dependencies defined in composer.json.

### 4. Create db.php from the example file

The file db.php is not included in the repository for security reasons. Instead there is a template file db.example.php.

Copy it to db.php:

    cp db.example.php db.php

Then open db.php in a text editor and set:

Database connection details:

    $host = 'localhost';
    $db   = 'your_database_name';
    $user = 'your_database_user';
    $pass = 'your_database_password';

Gmail SMTP configuration for PHPMailer:

    const GMAIL_SMTP_USER = 'your_gmail_address@example.com';
    const GMAIL_SMTP_PASS = 'your_gmail_app_password_here';
    const GMAIL_FROM_NAME = 'LoveJoy Support';

Google reCAPTCHA keys:

    const RECAPTCHA_SITE_KEY   = 'your_recaptcha_site_key_here';
    const RECAPTCHA_SECRET_KEY = 'your_recaptcha_secret_key_here';

Make sure you use a Gmail App Password, not your main Gmail login password.

### 5. Create the database and tables

1. Open phpMyAdmin or another MySQL client.
2. Create a new database, for example:

       CREATE DATABASE comp_sec_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

3. Select this database and create the tables `users`, `evaluation_requests`, and `password_resets` according to the column descriptions above.

You can either:
- Recreate the tables manually based on the definitions, or
- Export the table structure from an existing local instance and import it into this database.

### 6. Place the project in your web root

If you are using XAMPP on Windows:

- Move or copy the project folder into:

      C:\xampp\htdocs\

For example:

      C:\xampp\htdocs\lovejoy-app

### 7. Start Apache and MySQL

- Open the XAMPP Control Panel.
- Start Apache.
- Start MySQL.

### 8. Access the application in a browser

Navigate to:

    http://localhost/lovejoy-app/index.php

Adjust the folder name if your project folder has a different name.

---

## Using the Application

### Register a new user

1. Go to the registration page (link from index or login page).
2. Fill in email, password, full name, phone number.
3. Select a security question and answer.
4. Submit and resolve any password strength errors.
5. On success, an account is created in the users table.

### Create an admin user

There are two options:

- Option 1: Register a normal user and then in phpMyAdmin update its role column to "admin".
- Option 2: Insert an admin user manually via SQL with a valid password_hash.

An admin can access the evaluations list page and see all requests.

### Login and 2FA

1. Log in with your email and password on login.php.
2. Complete reCAPTCHA if present.
3. If the account has 2FA enabled, you will be redirected to the 2FA verification page and must enter a valid 6 digit code from the Google Authenticator app.

To enable 2FA:

- After logging in, visit the enable_2fa page.
- Scan the QR code with Google Authenticator.
- Enter a code from the app to confirm and enable two factor authentication on the account.

### Submit an evaluation request

1. Log in as a normal user.
2. Click the link to submit an evaluation request.
3. Fill in description, choose contact method, and optionally upload a photo.
4. Submit the form.
5. The request is stored in the evaluation_requests table. The file is stored in the uploads directory with a unique filename.

### View evaluation requests (admin only)

1. Log in as a user with role "admin".
2. Click the link to view all evaluation requests.
3. A table is shown with details for each request and links to view uploaded images.

### Password reset flow

1. Go to the forgot password page.
2. Enter your registered email.
3. If the email exists, a reset token and expiry time are stored in password_resets and an email is sent with a reset link.
4. Open the link. You will see your security question.
5. Enter the correct answer and a new strong password.
6. On success, the password_hash in users is updated and the token is deleted.

---

## Notes

- This application is intended for learning and demonstration purposes.
- For real deployment, you should:
  - Use HTTPS.
  - Harden PHP and server configuration.
  - Rotate any keys or credentials used during development.
  - Review and log security related events.
