<?php
// 1. Start the session to access it
session_start();

// 2. Unset all session variables
$_SESSION = array();

// 3. Destroy the session completely
session_destroy();

// 4. Redirect the user back to the login page
header("Location: login.php");
exit;
?>