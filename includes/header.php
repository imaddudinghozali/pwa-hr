<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="theme-color" content="#16a34a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="SIMK PHA">
<link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/icons/icon-192.png">
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/icons/icon-72.png">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — SIMK PHA</title>
</head>
<body>
<?php
// Guard: currentUser() must return valid user or redirect
$user = currentUser();
if (!$user) { redirect(BASE_URL.'/login.php'); }
$isAdmin = in_array($user['role'], ['admin','hrd'], true);

// Sidebar badge counts (safe — won't crash if tables empty)
$badgeLembur  = 0;
$badgeCuti    = 0;
$badgeNotif   = 0;
$badgeReimb   = 0;
$badgeChat    = 0;
$badgeKontrak = 0;
if ($isAdmin) {
    $r = db()->query("SELECT COUNT(*) c FROM lembur WHERE status='pending'");
    $badgeLembur = $r ? (int)$r->fetch_assoc()['c'] : 0;
    $r = db()->query("SELECT COUNT(*) c FROM pengajuan_cuti WHERE status='pending'");
    $badgeCuti   = $r ? (int)$r->fetch_assoc()['c'] : 0;
    $r = db()->query("SELECT COUNT(*) c FROM reimburse WHERE status='pending'");
    $badgeReimb  = $r ? (int)$r->fetch_assoc()['c'] : 0;
    $r = db()->query("SELECT COUNT(*) c FROM users WHERE jenis_karyawan='kontrak' AND tanggal_kontrak_selesai BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND status='aktif'");
    $badgeKontrak = $r ? (int)$r->fetch_assoc()['c'] : 0;
    // Chat unread for admin
    $r = db()->query("SELECT COUNT(*) c FROM chat_messages cm JOIN chat_room_members crm ON crm.room_id=cm.room_id AND crm.user_id={$user['id']} WHERE cm.sender_id!={$user['id']} AND (crm.last_read_at IS NULL OR cm.created_at>crm.last_read_at)");
    $badgeChat = $r ? (int)$r->fetch_assoc()['c'] : 0;
} else {
    $r = db()->query("SELECT COUNT(*) c FROM notifikasi WHERE user_id={$user['id']} AND sudah_dibaca=0");
    $badgeNotif  = $r ? (int)$r->fetch_assoc()['c'] : 0;
    $r = db()->query("SELECT COUNT(*) c FROM chat_messages cm JOIN chat_room_members crm ON crm.room_id=cm.room_id AND crm.user_id={$user['id']} WHERE cm.sender_id!={$user['id']} AND (crm.last_read_at IS NULL OR cm.created_at>crm.last_read_at)");
    $badgeChat = $r ? (int)$r->fetch_assoc()['c'] : 0;
}
?>
<div class="app-layout">

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">PH</div>
        <div class="brand-text">
            <div class="brand-name">SIMK PHA</div>
            <div class="brand-sub">PT Pesta Hijau Abadi</div>
        </div>
    </div>

    <nav class="sidebar-nav">
    <?php if ($isAdmin): ?>
        <div class="nav-section">
            <div class="nav-section-label">Utama</div>
            <a class="nav-item <?= ($activePage??'')==='dashboard'?'active':'' ?>" href="<?= BASE_URL ?>/pages/admin/dashboard.php">
                <span class="nav-icon">▦</span> Dashboard
            </a>
            <a class="nav-item <?= ($activePage??'')==='karyawan'?'active':'' ?>" href="<?= BASE_URL ?>/pages/admin/karyawan.php">
                <span class="nav-icon">▦</span> Karyawan
            </a>
        </div>
        <div class="nav-section">
            <div class="nav-section-label">Operasional</div>
            <a class="nav-item <?= ($activePage??'')==='absensi'?'active':'' ?>" href="<?= BASE_URL ?>/pages/admin/absensi.php">
                <span class="nav-icon">◉</span> Rekap Absensi
            </a>
            <a class="nav-item <?= ($activePage??'')==='lembur'?'active':'' ?>" href="<?= BASE_URL ?>/pages/admin/lembur.php">
                <span class="nav-icon">◉</span> Lembur
                <?php if ($badgeLembur > 0): ?><span class="badge"><?= $badgeLembur ?></span><?php endif; ?>
            </a>
            <a class="nav-item <?= ($activePage??'')==='cuti'?'active':'' ?>" href="<?= BASE_URL ?>/pages/admin/cuti.php">
                <span class="nav-icon">◉</span> Pengajuan Cuti
                <?php if ($badgeCuti > 0): ?><span class="badge"><?= $badgeCuti ?></span><?php endif; ?>
            </a>
            <a class="nav-item <?= ($activePage??'')==='reimburse'?'active':'' ?>" href="<?= BASE_URL ?>/pages/admin/reimburse.php">
                <span class="nav-icon">◉</span> Reimburse
                <?php if ($badgeReimb > 0): ?><span class="badge"><?= $badgeReimb ?></span><?php endif; ?>
            </a>
        </div>
        <div class="nav-section">
            <div class="nav-section-label">Penggajian</div>
            <a class="nav-item <?= ($activePage??'')==='penggajian'?'active':'' ?>" href="<?= BASE_URL ?>/pages/admin/penggajian.php">
                <span class="nav-icon">◉</span> Slip Gaji
            </a>
        </div>
        <div class="nav-section">
            <div class="nav-section-label">Komunikasi</div>
            <a class="nav-item <?= ($activePage??'')==='chat'?'active':'' ?>" href="<?= BASE_URL ?>/pages/admin/chat.php">
                <span class="nav-icon">◉</span> Chat
                <?php if ($badgeChat > 0): ?><span class="badge"><?= $badgeChat ?></span><?php endif; ?>
            </a>
        </div>
        <div class="nav-section">
            <div class="nav-section-label">Master Data</div>
            <a class="nav-item <?= ($activePage??'')==='departemen'?'active':'' ?>" href="<?= BASE_URL ?>/pages/admin/departemen.php">
                <span class="nav-icon">◉</span> Departemen
            </a>
            <a class="nav-item <?= ($activePage??'')==='shift'?'active':'' ?>" href="<?= BASE_URL ?>/pages/admin/shift.php">
                <span class="nav-icon">◉</span> Shift Kerja
            </a>
            <a class="nav-item <?= ($activePage??'')==='export'?'active':'' ?>" href="<?= BASE_URL ?>/pages/admin/export_karyawan.php">
                <span class="nav-icon">◉</span> Export Data
                <?php if ($badgeKontrak > 0): ?><span class="badge badge-amber" style="background:var(--amb-bg);color:#fcd34d"><?= $badgeKontrak ?></span><?php endif; ?>
            </a>
        </div>
    <?php else: ?>
        <div class="nav-section">
            <div class="nav-section-label">Menu Saya</div>
            <a class="nav-item <?= ($activePage??'')==='beranda'?'active':'' ?>" href="<?= BASE_URL ?>/pages/karyawan/dashboard.php">
                <span class="nav-icon">◉</span> Beranda
            </a>
            <a class="nav-item <?= ($activePage??'')==='absensi'?'active':'' ?>" href="<?= BASE_URL ?>/pages/karyawan/absensi.php">
                <span class="nav-icon">◉</span> Absensi Kamera
            </a>
            <a class="nav-item <?= ($activePage??'')==='lembur'?'active':'' ?>" href="<?= BASE_URL ?>/pages/karyawan/lembur.php">
                <span class="nav-icon">◉</span> Lembur
            </a>
            <a class="nav-item <?= ($activePage??'')==='cuti'?'active':'' ?>" href="<?= BASE_URL ?>/pages/karyawan/cuti.php">
                <span class="nav-icon">◉</span> Pengajuan Cuti
            </a>
            <a class="nav-item <?= ($activePage??'')==='reimburse'?'active':'' ?>" href="<?= BASE_URL ?>/pages/karyawan/reimburse.php">
                <span class="nav-icon">◉</span> Reimburse
            </a>
            <a class="nav-item <?= ($activePage??'')==='chat'?'active':'' ?>" href="<?= BASE_URL ?>/pages/karyawan/chat.php">
                <span class="nav-icon">◉</span> Chat HR
                <?php if ($badgeChat > 0): ?><span class="badge"><?= $badgeChat ?></span><?php endif; ?>
            </a>
            <a class="nav-item <?= ($activePage??'')==='gaji'?'active':'' ?>" href="<?= BASE_URL ?>/pages/karyawan/slip_gaji.php">
                <span class="nav-icon">◉</span> Slip Gaji
            </a>
            <a class="nav-item <?= ($activePage??'')==='profil'?'active':'' ?>" href="<?= BASE_URL ?>/pages/karyawan/profil.php">
                <span class="nav-icon">◉</span> Profil Saya
            </a>
        </div>
    <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar av-md" style="background:<?= avatarBg((int)$user['id']) ?>">
                <?= htmlspecialchars(initials($user['nama'])) ?>
            </div>
            <div class="user-info" style="flex:1;min-width:0;overflow:hidden">
                <div class="name" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars($user['nama']) ?>
                </div>
                <div class="role"><?= strtoupper($user['role']) ?> · <?= htmlspecialchars($user['nip']) ?></div>
            </div>
            <a href="<?= BASE_URL ?>/logout.php" title="Logout"
               style="color:var(--text-m);font-size:18px;padding:4px;flex-shrink:0;text-decoration:none">⏻</a>
        </div>
    </div>
</aside>

<div class="main-wrap">
    <header class="topbar">
        <button class="topbar-menu-btn" id="btn-sidebar-toggle">☰</button>
        <div style="flex:1;min-width:0">
            <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
            <?php if (!empty($pageSub)): ?>
            <div class="topbar-subtitle"><?= htmlspecialchars($pageSub) ?></div>
            <?php endif; ?>
        </div>
        <div class="topbar-right">
            <?= $topbarActions ?? '' ?>
            <a href="<?= BASE_URL ?>/pages/karyawan/notifikasi.php" class="notif-btn" title="Notifikasi">
                🔔<?php if ($badgeNotif > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
        </div>
    </header>

    <div class="page-content">
    <?php
    $flash = getFlash();
    if ($flash):
        $fc = ['success'=>'alert-success','error'=>'alert-error','info'=>'alert-info','amber'=>'alert-amber'];
        $cls = $fc[$flash['type']] ?? 'alert-info';
    ?>
    <div class="alert <?= $cls ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>
