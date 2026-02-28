<?php
session_start();         // start session 
require 'db.php';        // for database connection


$loginerrors = [];

// simple CSRF token for the login form
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $logincsrftoken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $logincsrftoken;
} else {
    $logincsrftoken = $_SESSION['csrf_token'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // check CSRF token 
    $postedToken = $_POST['csrf_token'] ?? '';
    if (empty($postedToken) || !hash_equals($logincsrftoken, $postedToken)) {
        $loginerrors[] = "Invalid submission. try again.";
    }

    // verify captcha
    if (empty($loginerrors)) {
        $captchatoken = $_POST['g-recaptcha-response'] ?? '';

        if ($captchatoken === '') {
            $loginerrors[] = "Please do the Captcha verification.";
        } else {
            
            $captchaverify = 'https://www.google.com/recaptcha/api/siteverify';

            $postData = http_build_query([
                'secret'   => RECAPTCHA_SECRET_KEY,
                'response' => $captchatoken,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => $postData,
                    'timeout' => 5,
                ]
            ];

            $context  = stream_context_create($options);
            $result   = @file_get_contents($captchaverify, false, $context);

            if ($result === false) {
                $loginerrors[] = "Could not verify CAPTCHA. try again.";
            } else {
                $decoded = json_decode($result, true);
                if (empty($decoded['success'])) {
                    $loginerrors[] = "CAPTCHA verification failed. try again.";
                }
            }
        }
    }

    // get email and password from the form
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // basic validation checks to enter in both fields
    if (empty($loginerrors)) {
        if ($email === '' || $password === '') {
            $loginerrors[] = "Please enter both email and password.";
        } else {
            // Fetch user from the database by email
            
            $stmt = $pdo->prepare("SELECT id, email, password_hash, name, role, failed_attempts, locked_until, twofa_enabled FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {

                // check if the account is currently locked
            
                if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
                    $loginerrors[] = "Your account is locked due to multiple failed login attempts. Please try again later.";
                } else {

                    // if the user exists check if the password is correct
                    if (password_verify($password, $user['password_hash'])) {
                        // reset failed attempts and lock if the login is successful 
                        $stmtreset = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
                        $stmtreset->execute([$user['id']]);

                        // check if this user has 2FA enabled
                        if (!empty($user['twofa_enabled'])) {
                            // store a temporary "pending" login and send them to 2FA verification page
                            $_SESSION['pending_2fa_user_id'] = $user['id'];
                            $_SESSION['pending_2fa_name']    = $user['name'];
                            $_SESSION['pending_2fa_role']    = $user['role'];

                            header('Location: verify_2fa.php');
                            exit;
                        } else {
                            // if there is no 2FA login normally
                            $_SESSION['user_id']   = $user['id'];
                            $_SESSION['user_name'] = $user['name'];
                            $_SESSION['user_role'] = $user['role'];  

                            // redirect to home page after signing in
                            header('Location: index.php');
                            exit;
                        }

                    } else {
                        // in the case of an incorrect password increase failed attempts
                        $failedattempts = (int)$user['failed_attempts'];
                        $newFails = $failedattempts + 1;

                        // if the user fails 5 times they will be locked out for 15 mins
                        $attemptthreshhold = 5;
                        $lockouttime = 15;

                        if ($newFails >= $attemptthreshhold) {
                            $lockedUntil = date('Y-m-d H:i:s', time() + $lockouttime * 60);

                            $stmtupdate = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = ? WHERE id = ?");
                            $stmtupdate->execute([$lockedUntil, $user['id']]);

                            $loginerrors[] = "Your account has been locked due to many failed  attempts. try again in $lockouttime minutes.";
                        } else {
                            $stmtupdate = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
                            $stmtupdate->execute([$newFails, $user['id']]);

                            // give a very vague error without giving too many details for security purposes
                            $loginerrors[] = "Invalid email or password.";
                        }
                    }
                }
            } else {
                // if the email is not found, still return a vague error
                $loginerrors[] = "Invalid email or password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <h1>User Login</h1>

    <?php if ($loginerrors): ?>
        <ul style="color:red;">
            <?php foreach ($loginerrors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<!--form html-->
    <form action="login.php" method="post">
        
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logincsrftoken) ?>">

        <div>
            <label for="email">Email address:</label><br>
            <input type="email" id="email" name="email"
                   value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
        </div>

        <div>
            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password" required>
        </div>

        
        <div class="g-recaptcha"
             data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE_KEY) ?>">
        </div>

        <br>
        <button type="submit">Login</button>
    </form>

    <!--links to forgot password and registration-->
    <p><a href="forgot_password.php">Forgot your password?</a></p>
    <p>Don't have an account? <a href="registeration.php">Register here</a></p>

</body>
</html>
