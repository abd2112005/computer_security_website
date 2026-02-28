<?php
session_start();        

//Remove all session variables
session_unset();

//Destroy the session completely
session_destroy();

//delete session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

//redirect to login page
header('Location: login.php');
exit;
