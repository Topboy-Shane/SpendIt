<?php
session_start();

// Unset all session variables
//HERE WE GO AGAIN WITH THE BUGS
$_SESSION = [];

// Destroy the session
session_destroy();

// Delete remember email cookie (match your login code)
setcookie('remember_email', '', time() - 3600, '/');

// Redirect to login
header("Location: Login-Register.php");
exit;
?>
