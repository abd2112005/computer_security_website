<?php

// Autoload Composer dependencies (Google2FA, PHPMailer, etc.)
require_once __DIR__ . '/vendor/autoload.php';

// ------------------------------------------------------------
// Mailing and CAPTCHA configuration
// ------------------------------------------------------------
// Copy this file to db.php and replace the placeholder values
// with your own credentials and keys.

const GMAIL_SMTP_HOST = 'smtp.gmail.com';
const GMAIL_SMTP_PORT = 587;

// Replace with your Gmail address used for sending emails
const GMAIL_SMTP_USER = 'your_gmail_address@example.com';

// Replace with your Gmail App Password (NOT your normal Gmail password)
const GMAIL_SMTP_PASS = 'your_gmail_app_password_here';

// Name that will appear in the "From" field of outgoing emails
const GMAIL_FROM_NAME  = 'LoveJoy Support';

// Google reCAPTCHA v2 keys
// Site key is used in HTML forms, secret key is used on the server.
const RECAPTCHA_SITE_KEY   = 'your_recaptcha_site_key_here';
const RECAPTCHA_SECRET_KEY = 'your_recaptcha_secret_key_here';

// ------------------------------------------------------------
// Database connection settings
// ------------------------------------------------------------
// Replace these values with your own database details.

$host = 'localhost';
$db   = 'comp_sec_db';
$user = 'your_db_username';
$pass = 'your_db_password';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // In production, don't echo the full error, just a generic message
    die("Database connection failed.");
}