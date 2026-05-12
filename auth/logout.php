<?php
session_start();
require_once dirname(__DIR__) . '/config/config.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Destroy the session
session_destroy();

// Redirect to login
header('Location: ' . LOGIN_URL);
exit();
?>
