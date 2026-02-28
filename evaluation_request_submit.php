<?php
//page to submit evaluation request

session_start();//start session
require 'db.php';// for access to database

// if user isnt logged in redirect to login screen
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// variables to hold the state of the form and the messages
$formErrors = [];
$formSavedSuccessfully = false;

$itemDetails = '';
$contactPreference = 'email'; // default selection 
$imagepath = null;

// csrf proetection
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // when the form is first loaded, create a fresh token and store it in the session
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
} else {
    // on the POST we will read the token that was stored earlier
    $csrfToken = $_SESSION['csrf_token'] ?? '';
}

// handles the submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // check the CSRF token before doing anything else
    $postedToken = $_POST['csrf_token'] ?? '';
    if (empty($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        $formErrors[] = "Invalid  submission. try again.";
    }

    // normalize the user input
    $itemDetails       = trim($_POST['description'] ?? '');
    $contactPreference = $_POST['preferred_contact'] ?? 'email';

    // basic validation checks

    if ($itemDetails === '') {
        $formErrors[] = "Please provide a description of the item.";
    }

    $allowedContactOptions = ['email', 'phone'];
    if (!in_array($contactPreference, $allowedContactOptions, true)) {
        $formErrors[] = "Selected contact method is not valid.";
    }

    
    // checks if a file was even selected in the first place
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {

        //checks if any errors occored with the uploading of the file
        if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {

            $tempFilePath = $_FILES['photo']['tmp_name'];
            $originalFileName = $_FILES['photo']['name'];
            $fileSizeBytes = $_FILES['photo']['size'];

            // limit the file size to protect against ddos attacks
            $maximumFileSize = 3 * 1024 * 1024; // 3MB in bytes

            if ($fileSizeBytes > $maximumFileSize) {
                $formErrors[] = "The uploaded image is too big in size. Maximum allowed size is 3 MB.";
            } else {
                // Check extensions to make the uploaded file is an image
                $Extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (!in_array($Extension, $allowedExtensions, true)) {
                    $formErrors[] = "Only image files (JPG, JPEG, PNG, GIF) can be uploaded.";
                } else {
                    // generate unqiue name for the file to avoid collisions
                    $uniqueFileName = uniqid('eval_', true) . '.' . $Extension;

                    // Build the file path
                    $targetDirectory = __DIR__ . '/uploads/';
                    $finalFilePath = $targetDirectory . $uniqueFileName;

                    // move the file from the temporary to the final path 
                    if (move_uploaded_file($tempFilePath, $finalFilePath)) {
                        
                        $imagepath = 'uploads/' . $uniqueFileName;
                    } else {
                        $formErrors[] = "There was an issue saving the uploaded image file.";
                    }
                }
            }
        } else {
            $formErrors[] = "An error occurred during the file upload process.";
        }
    }

    // if it passes all validation checks we upload it to the database using a prepared statement 
    if (empty($formErrors)) {
        $insertSql = "
            INSERT INTO evaluation_requests (user_id, description, preferred_contact, photo_path)
            VALUES (?, ?, ?, ?)
        ";
// always use prepared stataments to protect from sql injections
        $insertStatement = $pdo->prepare($insertSql);

        try {
            $insertStatement->execute([
                $_SESSION['user_id'],  
                $itemDetails,          
                $contactPreference,    
                $imagepath       
            ]);

           // mark the upload as succesful and reset form variables
            $formSavedSuccessfully = true;
            $itemDetails = '';
            $contactPreference = 'email';
            $imagepath = null;

        } catch (PDOException $dbError) {
            
            $formErrors[] = "We could not save your request. try again later.";
        }
    }
}

$currentUserName = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Evaluation Request Page</title>
</head>
<body>
    <h1>Submit an Evaluation Request</h1>

    <p>
        Signed in as:
        <strong><?= htmlspecialchars($currentUserName) ?></strong>
        (<a href="logout.php">Logout</a> | <a href="index.php">Home</a>)
    </p>

    <?php if ($formSavedSuccessfully): ?>
        <p style="color: green;">Your request has been submitted.</p>
    <?php endif; ?>

    <?php if (!empty($formErrors)): ?>
        <ul style="color: red;">
            <?php foreach ($formErrors as $message): ?>
                <li><?= htmlspecialchars($message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    
    <form action="request_evaluation.php" method="post" enctype="multipart/form-data">
        
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div>
            <label for="description">Describe the item and what you would like us to evaluate:</label><br>
            <textarea
                id="description"
                name="description"
                rows="5"
                cols="60"
                required
            ><?= htmlspecialchars($itemDetails) ?></textarea>
        </div>

        <div>
            <label for="preferred_contact">How do you want to be contacted?</label><br>
            <select id="preferred_contact" name="preferred_contact">
                <option value="email" <?= $contactPreference === 'email' ? 'selected' : '' ?>>Email</option>
                <option value="phone" <?= $contactPreference === 'phone' ? 'selected' : '' ?>>Phone</option>
            </select>
        </div>

        <div>
            <label for="photo">Please Upload a picture of the item (optional):</label><br>
            <input type="file" id="photo" name="photo" accept="image/*">
        </div>

        <br>
        <button type="submit">Send Request</button>
    </form>
</body>
</html>
