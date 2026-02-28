<?php
session_start();// start session
require 'db.php';//include db
require 'evaluate_password.php';//in order to use the password strength checker 

$errors = [];
$message = "";
$passwordFeedback   = null;
$securityQuestion   = "";
$userData            = null;

// Token must be present
$token = $_GET['token'] ?? '';

if ($token === '') {
    $errors[] = "Invalid password reset link.";
} else {
    // Look up the reset token, join with users to get security question and answer hash
    //always use prepared statement to avoid sql injection attacks
    $stmt = $pdo->prepare("
        SELECT 
            pr.user_id,
            u.security_question,
            u.security_answer_hash
        FROM password_resets AS pr
        INNER JOIN users AS u
            ON pr.user_id = u.id
        WHERE pr.token = ? 
          AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $userData = $stmt->fetch();

    if (!$userData) {
        $errors[] = "This reset link is invalid or expired.";
    } else {
        $securityQuestion = $userData['security_question'] ?? '';
    }
}

// handle the actual reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $securityAnswer = trim($_POST['security_answer'] ?? '');
    $newPassword    = $_POST['password'] ?? '';
    $confirm        = $_POST['confirm_password'] ?? '';

    if ($securityAnswer === '' || $newPassword === '' || $confirm === '') {
        $errors[] = "Please fill in all fields.";
    } elseif ($newPassword !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    // Only continue if no basic errors yet and token still valid
    if (empty($errors) && $userData) {
        // Check password strength
        $passwordFeedback = evaluatePasswordStrength($newPassword);
        if ($passwordFeedback['score'] <= 2) {
            $errors[] = "Your password is weak. Please follow some of the recommendations below.";
        } else {
            // Verify security answer
            if (!password_verify($securityAnswer, $userData['security_answer_hash'])) {
                $errors[] = "Incorrect answer.";
            } else {
                // Hash new password
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update user's password
                $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $upd->execute([$newHash, $userData['user_id']]);

                // Delete the token so it can't be reused
                $del = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                $del->execute([$token]);

                $message = "Your password has been reset successfully. You can now <a href=\"login.php\">login</a>.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
</head>
<body>
    <h1>Reset Password</h1>

    <?php if ($errors): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($message): ?>
        <p><?= $message ?></p>
    <?php endif; ?>

    <?php if (!$message && $token !== '' && $userData): ?>
        <form action="password_reset.php?token=<?= htmlspecialchars($token) ?>" method="post">
            <div>
                <p><strong>Security question:</strong> <?= htmlspecialchars($securityQuestion) ?></p>
                <label for="security_answer">Your answer:</label><br>
                <input type="text" id="security_answer" name="security_answer" required>
            </div>

            <div>
                <label for="password">New password:</label><br>
                <input type="password" id="password" name="password" required>
            </div>

            

            <div>
                <label for="confirm_password">Confirm new password:</label><br>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <?php if ($passwordFeedback): ?>
                <p>Password strength : <strong><?= htmlspecialchars($passwordFeedback['label']) ?></strong></p>
                <?php if ($passwordFeedback['requirements']): ?>
                    <ul>
                        <?php foreach ($passwordFeedback['requirements'] as $req): ?>
                            <li><?= htmlspecialchars($req) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

            <br>
            <button type="submit">Reset Password</button>
        </form>

    <?php endif; ?>



</body>
</html>
