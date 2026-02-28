<?php
session_start();//start session
require 'db.php';// require the file that contains the config for 2fa

use PragmaRX\Google2FA\Google2FA;

$errors  = [];
$message = "";

// if there is no pending 2FA login send back to login page
if (!isset($_SESSION['pending_2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['pending_2fa_user_id'];

// fetch user including 2FA secret and role/name
$stmt = $pdo->prepare("SELECT id, name, role, twofa_secret, twofa_enabled FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || empty($user['twofa_enabled']) || empty($user['twofa_secret'])) {
    // if something goes wrong clear pending and go back to login
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_name'], $_SESSION['pending_2fa_role']);
    header('Location: login.php');
    exit;
}

$google2fa = new Google2FA();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if ($code === '') {
        $errors[] = "Please enter the code from your google authenticator app.";
    } else {
        if ($google2fa->verifyKey($user['twofa_secret'], $code)) {
            // if 2fa succesful complete the login
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $_SESSION['pending_2fa_name'] ?? $user['name'];
            $_SESSION['user_role'] = $_SESSION['pending_2fa_role'] ?? $user['role'];

            // clear the pending 2FA data
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_name'], $_SESSION['pending_2fa_role']);

            header('Location: index.php');
            exit;
        } else {
            $errors[] = "The authentication code is not correct.try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Two-Factor Verification</title>
</head>
<body>
    <h1>Two-Factor Authentication</h1>

    <p>Please open your Google Authenticator app and enter the  code for your account.</p>

    <?php if (!empty($errors)): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form action="verify_2fa.php" method="post">
        <div>
            <label for="code">Authentication code:</label><br>
            <input type="text" id="code" name="code" required pattern="\d{6}" placeholder="11111">
        </div>
        <br>
        <button type="submit">Verify</button>
    </form>

    <p><a href="login.php">Back to Login</a></p>
</body>
</html>
