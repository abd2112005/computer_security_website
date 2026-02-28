<?php
// Page for a logged in user to enable 2FA

session_start();//start session
require 'db.php';//database connection as well as composer

use PragmaRX\Google2FA\Google2FA;

// must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors  = [];
$message = '';
$userId  = $_SESSION['user_id'];

// fetch current user data including email, 2fa secret and enabled flag
$stmt = $pdo->prepare("SELECT email, twofa_secret, twofa_enabled FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

$google2fa = new Google2FA();

// if user does not have a secret yet, generate one and store it
$secret = $user['twofa_secret'];
if (empty($secret)) {
    $secret = $google2fa->generateSecretKey();
    $upd = $pdo->prepare("UPDATE users SET twofa_secret = ? WHERE id = ?");
    $upd->execute([$secret, $userId]);
}

// build QR code
$qrText = $google2fa->getQRCodeUrl(
    'LoveJoy Jewellery',    
    $user['email'],         
    $secret                 
);

// generate qr image using quickchart
$qrImageUrl = 'https://quickchart.io/qr?text=' . urlencode($qrText) . '&size=300';

// handle form submission when user enters a code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if ($code === '') {
        $errors[] = "Please enter the code from the google authenticator app.";
    } else {
        // verify the code
        if ($google2fa->verifyKey($secret, $code)) {
            // enable 2FA for this user
            $upd = $pdo->prepare("UPDATE users SET twofa_enabled = 1 WHERE id = ?");
            $upd->execute([$userId]);

            $message = "Two-factor authentication has been enabled for your account.";
        } else {
            $errors[] = "The code you entered is not valid. Please try again.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enable Two-Factor Authentication</title>
</head>
<body>
    <h1>Enable Two-Factor Authentication (Google Authenticator)</h1>

    <p>
        Logged in as: <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></strong>
        (<?= htmlspecialchars($_SESSION['user_role'] ?? 'user') ?>) |
        <a href="index.php">Back to home</a> |
        <a href="logout.php">Logout</a>
    </p>

    <?php if ($message): ?>
        <p style="color: green;"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p>Scan this QR code with the Google Authenticator app.</p>

    <p>
        <img src="<?= htmlspecialchars($qrImageUrl) ?>" alt="2FA QR Code">
    </p>

    <p>
        If you cannot scan the QR code, enter the code manually
        <strong><?= htmlspecialchars($secret) ?></strong>
    </p>

    <p> Enter the code from the google authenticator app to confirm and enable 2FA:</p>

    <form action="enable_2fa.php" method="post">
        <div>
            <label for="code">Authentication code:</label><br>
            <input type="text" id="code" name="code" required pattern="\d{6}" placeholder="123456">
        </div>
        <br>
        <button type="submit">Confirm and Enable 2FA</button>
    </form>

    <?php if ((int)$user['twofa_enabled'] === 1): ?>
        <p style="color: green;">2FA is currently <strong>enabled</strong> on your account.</p>
    <?php else: ?>
        <p style="color: red;">2FA is currently <strong>not yet enabled</strong> you must enter a valid code.</p>
    <?php endif; ?>

</body>
</html>
