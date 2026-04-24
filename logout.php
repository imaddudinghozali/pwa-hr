<?php
session_start();
session_unset();
session_destroy();
// Destroy cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}
header('Location: /pwa-hr/login.php');
exit;
