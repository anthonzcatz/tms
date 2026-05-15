<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/Auth.php';

Auth::logout();

header('Location: ' . LOGIN_URL . '?success=logout_success');
exit;
?>
