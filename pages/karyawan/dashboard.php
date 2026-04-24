<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireLogin();
$user = currentUser();
if ($user['role'] !== 'karyawan') redirect(BASE_URL.'/pages/admin/dashboard.php');

$uid   = (int)$user['id'];
$hari  = date('Y-m-d');
$bulan = (int)date('m');
$tahun = (int)date('Y');

$absenHari = db()->query("SELECT * FROM absensi WHERE user_id=$uid AND tanggal='$hari' LIMIT 1")->fetch_assoc();
$rekap     = db()->query("SELECT SUM(status_kehadiran='hadir') hadir, SUM(status_masuk='terlambat') terlambat, SUM(status_kehadiran='cuti') cuti, SUM(status_kehadiran='alpha') alpha FROM absensi WHERE user_id=$uid AND MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun")->fetch_assoc();
$lemPend   = (int)db()->query("SELECT COUNT(*) c FROM lembur WHERE user_id=$uid AND status='pending'")->fetch_assoc()['c'];
$cutPend   = (int)db()->query("SELECT COUNT(*) c FROM pengajuan_cuti WHERE user_id=$uid AND status='pending'")->fetch_assoc()['c'];
$slip      = db()->query("SELECT * FROM slip_gaji WHERE user_id=$uid ORDER BY tahun DESC,bulan DESC LIMIT 1")->fetch_assoc();
$riwayat5  = db()->query("SELECT * FROM absensi WHERE user_id=$uid ORDER BY tanggal DESC LIMIT 5");

$pageTitle  = 'Beranda';
$pageSub    = 'Halo, '.explode(' ', $user['nama'])[0].'! 👋';
$activePage = 'beranda';
include __DIR__.'/../../includes/header.php';
?>

<!-- Profil Card -->
<div class="card mb-2" style="background:linear-gradient(135deg,var(--surface),var(--surface-2));border-color:var(--border-md)">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div class="avatar av-xl" style="background:<?=avatarBg($uid)?>"><?=initials($user['nama'])?></div>
        <div style="flex:1;min-width:200px">
            <div style="font-size:18px;font-weight:700"><?=htmlspecialchars($user['nama'])?></div>
            <div class="text-sm text-muted"><?=htmlspecialchars($user['jabatan_nama']??'—')?> · <?=htmlspecialchars($user['dept_nama']??'—')?></div>
            <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
                <?php if ($user['shift_nama']): ?>
                <span class="badge badge-purple"><?=htmlspecialchars($user['shift_nama'])?></span>
                <span class="badge badge-green"><?=substr($user['jam_masuk']??'',0,5)?> – <?=substr($user['jam_keluar']??'',0,5)?></span>
                <?php endif; ?>
                <span class="badge badge-blue">Cuti tersisa: <?=(int)$user['sisa_cuti']?> hari</span>
            </div>
        </div>
    </div>
</div>

<!-- Status absen hari ini -->
<div class="card mb-2">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <div style="font-size:11px;color:var(--text-m);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Status Absensi Hari Ini</div>
            <?php if ($absenHari): ?>
            <div class="text-sm" style="color:var(--text-2)">
                <?php if ($absenHari['jam_masuk']): ?>
                Masuk: <strong class="text-green mono"><?=date('H:i',strtotime($absenHari['jam_masuk']))?></strong>
                <?php endif; ?>
                <?php if ($absenHari['jam_keluar']): ?>
                &nbsp;· Keluar: <strong class="text-amber mono"><?=date('H:i',strtotime($absenHari['jam_keluar']))?></strong>
                <?php endif; ?>
                &nbsp;
                <?php $bs=['tepat'=>'badge-green','terlambat'=>'badge-amber','izin'=>'badge-blue','alpha'=>'badge-red'];
                echo "<span class='badge ".($bs[$absenHari['status_masuk']]??'badge-gray')."'>".ucfirst($absenHari['status_masuk'])."</span>"; ?>
            </div>
            <?php else: ?>
            <div class="text-muted text-sm">Belum absen hari ini</div>
            <?php endif; ?>
        </div>
        <a href="<?=BASE_URL?>/pages/karyawan/absensi.php" class="btn btn-primary">
            <?php if (!$absenHari): ?>▶ Absen Masuk
            <?php elseif (!$absenHari['jam_keluar']): ?>⏹ Absen Keluar
            <?php else: ?>✅ Lihat Absensi<?php endif; ?>
        </a>
    </div>
</div>

<!-- Stats -->
<div class="stat-grid mb-2">
    <div class="stat-card green"><div class="stat-icon">✅</div><div class="stat-label">Hadir Bulan Ini</div><div class="stat-value"><?=(int)$rekap['hadir']?></div></div>
    <div class="stat-card amber"><div class="stat-icon">⏰</div><div class="stat-label">Terlambat</div><div class="stat-value"><?=(int)$rekap['terlambat']?></div></div>
    <div class="stat-card blue"><div class="stat-icon">🏖</div><div class="stat-label">Cuti Dipakai</div><div class="stat-value"><?=(int)$rekap['cuti']?></div></div>
    <div class="stat-card green"><div class="stat-icon">💰</div><div class="stat-label">Gaji Terakhir</div><div class="stat-value" style="font-size:14px"><?=$slip?formatRp($slip['gaji_bersih']):'—'?></div><div class="stat-sub"><?=$slip?bulanNama($slip['bulan']).' '.$slip['tahun']:'belum ada'?></div></div>
</div>

<div class="grid-2">
    <!-- Riwayat 5 terakhir -->
    <div class="card">
        <div class="card-header"><span class="card-title">Absensi Terakhir</span><a href="<?=BASE_URL?>/pages/karyawan/absensi.php" class="btn btn-sm">Lihat semua</a></div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>Tanggal</th><th>Masuk</th><th>Keluar</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$riwayat5 || $riwayat5->num_rows===0): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text-m);padding:2rem">Belum ada absensi</td></tr>
            <?php else: while ($r=$riwayat5->fetch_assoc()):
                $bs=['tepat'=>'badge-green','terlambat'=>'badge-amber','alpha'=>'badge-red','izin'=>'badge-blue'];?>
            <tr>
                <td class="text-sm"><?=formatTgl($r['tanggal'])?></td>
                <td class="mono text-sm"><?=$r['jam_masuk']?date('H:i',strtotime($r['jam_masuk'])):'—'?></td>
                <td class="mono text-sm"><?=$r['jam_keluar']?date('H:i',strtotime($r['jam_keluar'])):'—'?></td>
                <td><span class="badge <?=$bs[$r['status_masuk']]??'badge-gray'?>"><?=ucfirst($r['status_masuk'])?></span></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table></div>
    </div>

    <!-- Quick links -->
    <div class="card">
        <div class="card-header"><span class="card-title">Menu Cepat</span></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
            <a href="<?=BASE_URL?>/pages/karyawan/absensi.php" class="btn btn-primary" style="justify-content:flex-start">📍 Absensi GPS</a>
            <a href="<?=BASE_URL?>/pages/karyawan/lembur.php" class="btn" style="justify-content:flex-start">
                ⏱ Ajukan Lembur
                <?php if ($lemPend>0): ?><span class="badge" style="background:var(--amber);color:#000"><?=$lemPend?> pending</span><?php endif; ?>
            </a>
            <a href="<?=BASE_URL?>/pages/karyawan/cuti.php" class="btn" style="justify-content:flex-start">
                🏖 Pengajuan Cuti
                <?php if ($cutPend>0): ?><span class="badge" style="background:var(--amber);color:#000"><?=$cutPend?> pending</span><?php endif; ?>
            </a>
            <a href="<?=BASE_URL?>/pages/karyawan/slip_gaji.php" class="btn" style="justify-content:flex-start">💵 Slip Gaji Saya</a>
            <a href="<?=BASE_URL?>/pages/karyawan/profil.php"    class="btn" style="justify-content:flex-start">👤 Profil & Password</a>
        </div>
    </div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
