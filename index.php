<?php
session_start();
require_once __DIR__.'/config/database.php';
if (isLoggedIn()) {
    $u = currentUser();
    redirect(BASE_URL.($u && $u['role'] !== 'karyawan'
        ? '/pages/admin/dashboard.php'
        : '/pages/karyawan/dashboard.php'));
}
redirect(BASE_URL.'/login.php');
