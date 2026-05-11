<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (isLoggedIn()) {
    $u = currentUser();
    $dest = ($u && $u['role'] !== 'karyawan')
          ? BASE_URL.'/pages/admin/dashboard.php'
          : BASE_URL.'/pages/karyawan/dashboard.php';
    redirect($dest);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip  = sanitize($_POST['nip']      ?? '');
    $pass = trim($_POST['password']     ?? '');

    if ($nip === '' || $pass === '') {
        $error = 'NIP/Email dan password wajib diisi.';
    } else {
        $stmt = dbPrepare("SELECT * FROM users WHERE (nip=? OR email=?) AND status='aktif' LIMIT 1");
        $stmt->bind_param('ss', $nip, $nip);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'NIP/Email tidak ditemukan atau akun non-aktif.';
        } elseif ($user['password'] === 'SETUP_REQUIRED') {
            $error = 'Password akun belum aktif. Hubungi HR/Admin untuk aktivasi akun.';
        } elseif (!password_verify($pass, $user['password'])) {
            $error = 'Password salah.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            unset($_SESSION['_user_cache']);   // clear stale cache
            db()->query("UPDATE users SET last_login=NOW() WHERE id=".intval($user['id']));
            $dest = ($user['role'] !== 'karyawan')
                  ? BASE_URL.'/pages/admin/dashboard.php'
                  : BASE_URL.'/pages/karyawan/dashboard.php';
            redirect($dest);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0f172a">
<link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/icons/icon-192.png">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
<title>Login &mdash; SIMK PHA</title>
</head>
<body>
<div class="login-page">
    <div class="login-bg-pattern"></div>
    <div class="login-card">
        <div class="login-logo">
            <div class="login-logo-icon">PH</div>
            <div class="login-company">PT Pesta Hijau Abadi</div>
            <div class="login-tagline">Sistem Informasi Manajemen Karyawan</div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:1rem"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <div class="form-group mb-2">
                <label class="form-label">NIP atau Email</label>
                <input type="text" name="nip" class="form-control"
                       placeholder="Masukkan NIP atau email"
                       value="<?= htmlspecialchars($_POST['nip'] ?? '') ?>"
                       autocomplete="username"
                       required autofocus>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Password</label>
                <div style="position:relative">
                    <input type="password" name="password" id="inp-pass" class="form-control"
                           placeholder="Masukkan password" autocomplete="current-password" required style="padding-right:42px">
                    <button type="button" onclick="togglePass(this)"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                               background:none;border:none;cursor:pointer;color:var(--text-m);font-size:16px"
                        aria-label="Tampilkan password"><span class="ui-icon i-eye"></span></button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-xl icon-label">Masuk <span class="ui-icon i-arrow-right"></span></button>
        </form>

        <div style="margin-top:1.5rem;padding:1rem;background:rgba(15,23,42,.42);
                    border:1px solid var(--border);border-radius:var(--r);font-size:12px;color:var(--text-m)">
            <div class="icon-label" style="color:var(--text-2);font-weight:700;margin-bottom:4px">
                <span class="ui-icon i-info"></span> Akses internal perusahaan
            </div>
            Gunakan akun yang diberikan oleh HR/Admin. Demi keamanan, jangan bagikan password kepada siapa pun.
        </div>
    </div>
</div>
<script>
function togglePass(btn) {
    var i = document.getElementById('inp-pass');
    i.type = i.type === 'password' ? 'text' : 'password';
    btn.innerHTML = i.type === 'password'
        ? '<span class="ui-icon i-eye"></span>'
        : '<span class="ui-icon i-eye-off"></span>';
    btn.setAttribute('aria-label', i.type === 'password' ? 'Tampilkan password' : 'Sembunyikan password');
}
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js');
}
</script>
</body>
</html>
