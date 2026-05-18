<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';

try {
    $auth = new AuthController();
    $auth->login();
} catch (Exception $e) {
    $_SESSION['error'] = 'Login failed. Please try again.';
    session_write_close();
    header('Location: ' . LOGIN_URL);
    exit;
}
?>
