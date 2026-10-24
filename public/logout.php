<?php
// Ends the customer's session and sends them to the login page.
// There is no visible page here: it only does its job and redirects.

session_start();

// 1. Remove every value stored in the session (customer id, name, group room code, and so on)
$_SESSION = [];

// 2. Delete the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destroy the session on the server
session_destroy();

// 4. Go to the login page, which shows "You have been logged out."
header('Location: login.php?success=loggedout');
exit;