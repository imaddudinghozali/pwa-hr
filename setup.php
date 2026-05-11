<?php
/**
 * SIMK PHA — Setup Password
 * Jalankan SEKALI: http://localhost/pwa-hr/setup.php
 * Hapus file ini setelah selesai!
 */
session_start();
require_once __DIR__.'/config/database.php';

$done   = false;
$errors = [];
$msgs   = [];

// Cek tabel users ada
$tableOK = false;
$r = db()->query("SHOW TABLES LIKE 'users'");
if ($r && $r->num_rows > 0) $tableOK = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableOK) {
    $passAdmin = trim($_POST['pass_admin'] ?? 'Admin@123');
    $passEmp   = trim($_POST['pass_emp']   ?? 'Admin@123');
    if (strlen($passAdmin) < 6) { $errors[] = 'Password admin minimal 6 karakter.'; }
    if (strlen($passEmp)   < 6) { $errors[] = 'Password karyawan minimal 6 karakter.'; }

    if (empty($errors)) {
        $accounts = [
            ['ADM001', $passAdmin],
            ['HRD001', $passAdmin],
            ['EMP001', $passEmp],
            ['EMP002', $passEmp],
            ['EMP003', $passEmp],
        ];
        foreach ($accounts as [$nip, $pw]) {
            $hash  = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 10]);
            $hEsc  = esc($hash);
            $nipEsc= esc($nip);
            $ok    = db()->query("UPDATE users SET password='$hEsc' WHERE nip='$nipEsc'");
            if ($ok && db()->affected_rows > 0) {
                $msgs[] = "✅ $nip — password berhasil diset";
            } elseif ($ok) {
                $msgs[] = "⚠️  $nip — NIP tidak ditemukan di database";
            } else {
                $errors[] = "❌ $nip — ".db()->error;
            }
        }
        $done = empty($errors);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup — SIMK PHA</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0a1a0a;color:#f0fdf4;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
.card{background:#0f2210;border:1px solid rgba(34,197,94,.25);border-radius:16px;padding:2rem;width:460px;max-width:100%}
h1{font-size:20px;font-weight:700;color:#4ade80;margin-bottom:4px}
.sub{font-size:13px;color:#86efac;margin-bottom:1.5rem}
label{display:block;font-size:12px;font-weight:600;color:#86efac;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
input[type=text],input[type=password]{width:100%;padding:9px 12px;background:#162b16;border:1px solid rgba(34,197,94,.25);border-radius:8px;color:#f0fdf4;font-size:14px;margin-bottom:14px}
input:focus{outline:none;border-color:#16a34a}
button{width:100%;padding:11px;background:#15803d;border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;margin-top:4px}
button:hover{background:#16a34a}
.ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:13px;line-height:1.8}
.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:13px}
.warn{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#fcd34d;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:12px}
.btn-login{display:block;text-align:center;margin-top:14px;padding:11px;background:#166534;border-radius:8px;color:#4ade80;font-weight:700;text-decoration:none;font-size:14px}
code{background:rgba(34,197,94,.1);padding:1px 6px;border-radius:4px;font-family:monospace;font-size:12px}
</style>
</head>
<body>
<div class="card">
    <h1>⚙️ Setup SIMK PHA</h1>
    <div class="sub">Inisialisasi password akun default</div>

    <?php if (!$tableOK): ?>
    <div class="err">
        <strong>Database belum diimport!</strong><br>
        Import <code>database.sql</code> ke MySQL terlebih dahulu, lalu reload halaman ini.
    </div>

    <?php elseif ($done): ?>
    <div class="ok">
        <strong>Setup berhasil!</strong><br>
        <?= implode('<br>', array_map('htmlspecialchars', $msgs)) ?>
    </div>
    <div class="warn">⚠️ <strong>Hapus file <code>setup.php</code> setelah login berhasil!</strong></div>
    <a href="<?= BASE_URL ?>/login.php" class="btn-login">→ Buka Halaman Login</a>

    <?php else: ?>
    <?php if (!empty($errors)): ?>
    <div class="err"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>
    <?php if (!empty($msgs)): ?>
    <div class="ok"><?= implode('<br>', array_map('htmlspecialchars', $msgs)) ?></div>
    <?php endif; ?>

    <div class="warn">
        Script ini set password untuk akun: <code>ADM001</code>, <code>HRD001</code>,
        <code>EMP001</code>, <code>EMP002</code>, <code>EMP003</code>.<br>
        Jalankan <strong>sekali saja</strong> setelah import database.
    </div>
    <form method="POST">
        <label>Password Admin & HRD (ADM001, HRD001)</label>
        <input type="text" name="pass_admin" value="Admin@123" required minlength="6">
        <label>Password Karyawan (EMP001–EMP003)</label>
        <input type="text" name="pass_emp" value="Admin@123" required minlength="6">
        <button type="submit">🔐 Set Password Sekarang</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
