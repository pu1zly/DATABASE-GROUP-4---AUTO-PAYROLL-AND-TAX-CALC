<?php
// logout.php - User Logout Handler
session_start();

// Clear session data
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
