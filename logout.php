<?php
session_start();
session_unset();
session_destroy();

// Delete all cookies by expiring them
setcookie('user', '', time() - 3600, "/");
setcookie('role', '', time() - 3600, "/");

header('Location: login.php');
exit;
