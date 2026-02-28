<?php
//start the session in order to access the session variables
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LoveJoy Jewellery Evaluation App</title>
</head>
<body>
    <h1>Welcome to LoveJoy</h1>
    
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- read the name and the role of the user from the ssion if they are logged in-->
        <p>
            Logged in as:
            <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></strong>
            (Role: <?= htmlspecialchars($_SESSION['user_role'] ?? 'user') ?>)
        </p>
<!--options for logged in user -->
        <ul><!--link to submit evalutaion request-->
            <li><a href="evaluation_request_submit.php">Submit a request for an evaluation</a></li>
            <!-- link to enable 2FA for this account -->
            <li><a href="enable_2fa.php">Enable Two-Factor Authentication</a></li>
<!--link to view evaluation requests only visible to admin role-->
            <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <!-- This will be for Task 5 -->
                <li><a href="evaluations.php">View all evaluation requests</a></li>
            <?php endif; ?>
<!--link to logout-->
            <li><a href="logout.php">Logout</a></li>
        </ul>

    <?php else: ?>
        <p>You are  currently not logged in.</p>
        <ul>
            <!--links to login and register pages if user is not logged in yet-->
            <li><a href="login.php">Login Page</a></li>
            <li><a href="registeration.php">Registeration Page</a></li>
        </ul>
    <?php endif; ?>

</body>
</html>
