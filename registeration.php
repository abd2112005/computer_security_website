<?php
//user registration page

session_start(); 
require 'db.php';
require 'evaluate_password.php';

$registrationMistakes = [];
$registrationscompleted = false;
$passwordstrength = null;

// repopulate form if validation fails
$EmailInput        = '';
$FullnameInput     = '';
$NumberInput  = '';
$SecurityQuestioninput = '';

// csrf token for preotection
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tokencsrf = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $tokencsrf;
} else {
    $tokencsrf = $_SESSION['csrf_token'] ?? '';
}

// form submission handling

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // check the CSRF token before processing
    $postedToken = $_POST['csrf_token'] ?? '';
    if (empty($postedToken) || !hash_equals($tokencsrf, $postedToken)) {
        $registrationMistakes[] = "Invalid submission. try again.";
    }

    // clean input
    $EmailInput       = trim($_POST['email']    ?? '');
    $inputPassword    = $_POST['password']      ?? '';
    $FullnameInput    = trim($_POST['name']     ?? '');
    $NumberInput = trim($_POST['phone']    ?? '');
    $SecurityQuestioninput = $_POST['security_question'] ?? '';
    $inputSecurityAnswer   = trim($_POST['security_answer'] ?? '');

    // run the password entropy check
    $passwordstrength = evaluatePasswordStrength($inputPassword);

    // validation check all fields must be filled
    if (
        $EmailInput === '' ||
        $inputPassword === '' ||
        $FullnameInput === '' ||
        $NumberInput === '' ||
        $SecurityQuestioninput === '' ||
        $inputSecurityAnswer === ''
    ) {
        $registrationMistakes[] = "All fields must be filled.";
    }

    // Email format validation
    if ($EmailInput !== '' && !filter_var($EmailInput, FILTER_VALIDATE_EMAIL)) {
        $registrationMistakes[] = "Please enter a valid email address.";
    }

    // enforce that the password strength mst be greater than two
    if ($passwordstrength['score'] <= 2) {
        $registrationMistakes[] = "Your password is considered weak. Please follow some of the suggestions below.";
    }

    // check if email is already registered to another account
    if (empty($registrationMistakes)) {
        $emailCheckSql = "SELECT id FROM users WHERE email = ?";
        $emailCheckStmt = $pdo->prepare($emailCheckSql);
        $emailCheckStmt->execute([$EmailInput]);

        if ($emailCheckStmt->fetch()) {
            $registrationMistakes[] = "An account with this email address already exists.";
        }
    }

    // if it passes all previous checks insert the new user
    if (empty($registrationMistakes)) {
        $hashedPassword      = password_hash($inputPassword, PASSWORD_DEFAULT);
        $defaultRole         = 'user';
        $securityAnswerHash  = password_hash($inputSecurityAnswer, PASSWORD_DEFAULT);

        $insertSql = "
            INSERT INTO users (email, password_hash, name, phone, role, security_question, security_answer_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
//always use prepared statements to protect from sql injections
        $insertStmt = $pdo->prepare($insertSql);

        try {
            $insertStmt->execute([
                $EmailInput,
                $hashedPassword,
                $FullnameInput,
                $NumberInput,
                $defaultRole,
                $SecurityQuestioninput,
                $securityAnswerHash,
            ]);

            // Mark success and clear form fields
            $registrationscompleted = true;
            $EmailInput        = '';
            $FullnameInput     = '';
            $NumberInput  = '';
            $SecurityQuestioninput = '';
            // Do not repopulate password field for security reasons
            $passwordstrength = null;

        } catch (PDOException $problem) {
            // give error message if something goes wrong with insertion
            $registrationMistakes[] = "We could not register your account at this moment. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create an Account</title>
</head>
<body>
    <h1>Account Registration</h1>

    <?php if ($registrationscompleted): ?>
        <p style="color: green;">Your account has been created successfully. You can now <a href="login.php">log in</a>.</p>
    <?php endif; ?>

    <?php if (!empty($registrationMistakes)): ?>
        <ul style="color: red;">
            <?php foreach ($registrationMistakes as $errorText): ?>
                <li><?= htmlspecialchars($errorText) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form action="registeration.php" method="post">
        
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($tokencsrf) ?>">

        <div>
            <label for="email">Email address:</label><br>
            <input
                type="email"
                id="email"
                name="email"
                required
                value="<?= htmlspecialchars($EmailInput) ?>"
            >
        </div>

        <div>
            <label for="password">Choose a password:</label><br>
            <input
                type="password"
                id="password"
                name="password"
                required
            >
        </div>

        

        
        <?php if (!empty($passwordstrength)): ?>
            <p>Password check: <strong><?= htmlspecialchars($passwordstrength['label']) ?></strong></p>
            <?php if (!empty($passwordstrength['requirements'])): ?>
                <ul>
                    <?php foreach ($passwordstrength['requirements'] as $tip): ?>
                        <li><?= htmlspecialchars($tip) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>

        <div>
            <label for="name">Full name:</label><br>
            <input
                type="text"
                id="name"
                name="name"
                required
                value="<?= htmlspecialchars($FullnameInput) ?>"
            >
        </div>

        <div>
            <label for="phone">Contact phone number:</label><br>
            <input
                type="text"
                id="phone"
                name="phone"
                required
                value="<?= htmlspecialchars($NumberInput) ?>"
            >
        </div>

        <div>
            <label for="security_question">Security question:</label><br>
            <select id="security_question" name="security_question" required>
                <option value="">-- Please select a question --</option>
                <option value="What was the name of your first pet?"
                    <?= $SecurityQuestioninput === "What was the name of your first pet?" ? 'selected' : '' ?>>
                    What was the name of your first pet?
                </option>
                <option value="What is the name of your primary school?"
                    <?= $SecurityQuestioninput === "What is the name of your primary school?" ? 'selected' : '' ?>>
                    What is the name of your primary school?
                </option>
                <option value="In what city were you born?"
                    <?= $SecurityQuestioninput === "In what city were you born?" ? 'selected' : '' ?>>
                    In what city were you born?
                </option>
            </select>
        </div>

        <div>
            <label for="security_answer">Answer:</label><br>
            <input
                type="text"
                id="security_answer"
                name="security_answer"
                required
            >
        </div>

        <br>
        <button type="submit">Register</button>
    </form>

    <p>Already have an account? <a href="login.php">Go to login</a></p>

    
    
</body>
</html>
