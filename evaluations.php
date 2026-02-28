<?php
//page to view all evaluation requets admin only

session_start();//start session
require 'db.php';//access to database

//must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// must be an admin
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo "You do not have permission to view this page .";
    exit;
}

$currentAdminName = $_SESSION['user_name'] ?? 'Admin';

// Fetch all evaluation requests 
try {
    $sql = "
        SELECT 
            er.id,
            er.description,
            er.preferred_contact,
            er.photo_path,
            er.created_at,
            u.name  AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone
        FROM evaluation_requests AS er
        INNER JOIN users AS u
            ON er.user_id = u.id
        ORDER BY er.created_at DESC
    ";

    $query = $pdo->query($sql);
    $allRequests = $query->fetchAll();

} catch (PDOException $dbProblem) {
    // give an error message if an issue occored with the fetching
    die("We were unable to retrieve the evaluation requests at this time.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Evaluation Requests</title>
    <style>
        table, th, td {
            border: 1px solid #555;
            border-collapse: collapse;
            padding: 6px 8px;
            vertical-align: top;
        }
        th {
            background-color:#f0f0f0;
        }
        tbody tr:nth-child(even) {
            background-color:#fafafa;
        }
    </style>
</head>
<body>
    <h1>Evaluation Requests </h1>

    <p>
        Logged in as:
        <strong><?= htmlspecialchars($currentAdminName) ?></strong>
        (admin)
        |
        <!-- link to return to home page-->
        <a href="index.php">Return to home</a>
        |
        <!--link to log out-->
        <a href="logout.php">Log out</a>
    </p>
<!--if there are no evaluation requets display this-->
    <?php if (empty($allRequests)): ?>
        <p>No evaluation requests have been submitted yet.</p>
    <?php else: ?>
        <!--else display them-->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Contact Details</th>
                    <th>Description</th>
                    <th>Preferred Contact</th>
                    <th>Photo</th>
                    <th>Submition date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allRequests as $requestRow): ?>
                    <tr>
                        <td><?= htmlspecialchars($requestRow['id']) ?></td>

                        <td>
                            <?= htmlspecialchars($requestRow['customer_name']) ?>
                        </td>

                        <td>
                            Email: <?= htmlspecialchars($requestRow['customer_email']) ?><br>
                            Phone: <?= htmlspecialchars($requestRow['customer_phone']) ?>
                        </td>

                        <td><?= nl2br(htmlspecialchars($requestRow['description'])) ?></td>

                        <td><?= htmlspecialchars(ucfirst($requestRow['preferred_contact'])) ?></td>

                        <td>
                            <?php if (!empty($requestRow['photo_path'])): ?>
                                <a href="<?= htmlspecialchars($requestRow['photo_path']) ?>" target="_blank">
                                    View image
                                </a>
                            <?php else: ?>
                                No image provided
                            <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars($requestRow['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>
