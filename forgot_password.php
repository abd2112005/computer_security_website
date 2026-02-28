<?php


session_start();// start session
require 'db.php';// include the db


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$userMessage = "";

// CSRF token for this form
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
} else {
    $csrfToken = $_SESSION['csrf_token'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (empty($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        $errors[] = "Invalid form submission. Please try again.";
    }

    $email = trim($_POST['email'] ?? '');

    // ensures email is not empty and  valid
    if ($email === '') {
        $errors[] = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($errors)) {
        // Look up user by email
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        //always show this vague message either way to avoid leaking if this user is in the database or not
        $userMessage = "If this email exists in our system, a password reset link will be sent.";

        if ($user) {
            // Generate token
            $token = bin2hex(random_bytes(16)); 
            $expiration = date('Y-m-d H:i:s', time() + 10*60); // 10 minutes from now

            // delete old tokens for this user
            $delete = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $delete->execute([$user['id']]);

            // Insert new token
            $insert = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $insert->execute([$user['id'], $token, $expiration]);

            // Build a reset link for the email
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basepath   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $reseturl = $protocol . '://' . $host . $basepath . '/password_reset.php?token=' . urlencode($token);

            // Send email via gmail
            $mail = new PHPMailer(true);

            try {
               
                $mail->isSMTP();
                $mail->Host       = GMAIL_SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = GMAIL_SMTP_USER;
                $mail->Password   = GMAIL_SMTP_PASS;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = GMAIL_SMTP_PORT;

                
                $mail->setFrom(GMAIL_SMTP_USER, GMAIL_FROM_NAME);
                $mail->addAddress($user['email']); // send to the user address
                
                $mail->isHTML(true);
                $mail->Subject = 'Password reset request';
                $mail->Body    = 'Hello,<br><br>'
                    . 'you have sent a request to reset your password.'
                    . '<br>Please click the link below to reset the password:<br><br>'
                    . '<a href="' . htmlspecialchars($reseturl) . '">Reset your password</a>'
                    . '<br><br>please ignore this email if you did not request to reset the password.';

                

                $mail->send();

                

            } catch (Exception $e) {
                // If email sending fails, show the link for testing
                $userMessage .= "<br><br>We could not send the email automatically ("
                          . htmlspecialchars($mail->ErrorInfo)
                          . "). For testing locally, you can use this link directly: "
                          . "<a href=\"" . htmlspecialchars($reseturl) . "\">Reset Password</a>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
</head>
<body>
    <h1>Forgot Password</h1>

    <?php if ($errors): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($userMessage): ?>
        <p><?= $userMessage ?></p>
    <?php endif; ?>

    <form action="forgot_password.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div>
            <label for="email">Enter your email address:</label><br>
            <input type="email" id="email" name="email" required>
        </div>
        <br>
        <button type="submit">Send reset link</button>
    </form>

    <p><a href="login.php">Back to Login</a></p>
</body>
</html>
