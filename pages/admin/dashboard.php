<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();

$hari  = date('Y-m-d');
$bulan = (int)date('m');
$tahun = (int)date('Y');

// Stats karyawan
$sKar = db()->query("SELECT
    COUNT(*) total,
    SUM(status='aktif') aktif,
    SUM(status='cuti') cuti
    FROM users WHERE role='karyawan'")->fetch_assoc();

// Hadir hari ini
$hadir = (int)db()->query("SELECT COUNT(DISTINCT user_id) c FROM absensi
    WHERE tanggal='$hari' AND status_kehadiran='hadir'")->fetch_assoc()['c'];

// Pending
$lemPend = (int)db()->query("SELECT COUNT(*) c FROM lembur WHERE status='pending'")->fetch_assoc()['c'];
$cutPend = (int)db()->query("SELECT COUNT(*) c FROM pengajuan_cuti WHERE status='pending'")->fetch_assoc()['c'];

// Total gaji bulan ini
$totGaji = (float)db()->query("SELECT COALESCE(SUM(gaji_bersih),0) t FROM slip_gaji
    WHERE bulan=$bulan AND tahun=$tahun")->fetch_assoc()['t'];

// Absensi hari ini
$absenHari = db()->query("SELECT a.*,u.nama,u.nip,d.nama dept_nama
    FROM absensi a
    JOIN users u ON a.user_id=u.id
    LEFT JOIN departemen d ON u.departemen_id=d.id
    WHERE a.tanggal='$hari'
    ORDER BY a.jam_masuk DESC LIMIT 10");

// Distribusi departemen
$deptDist = db()->query("SELECT d.nama,COUNT(u.id) jml
    FROM departemen d
    LEFT JOIN users u ON u.departemen_id=d.id AND u.role='karyawan' AND u.status='aktif'
    GROUP BY d.id,d.nama ORDER BY jml DESC");

// Pending lembur (5)
$lemList = db()->query("SELECT l.*,u.nama,u.nip FROM lembur l
    JOIN users u ON l.user_id=u.id WHERE l.status='pending'
    ORDER BY l.created_at DESC LIMIT 5");

// Pending cuti (5)
$cutList = db()->query("SELECT c.*,u.nama,jc.nama jenis_nama FROM pengajuan_cuti c
    JOIN users u ON c.user_id=u.id
    JOIN jenis_cuti jc ON c.jenis_cuti_id=jc.id
    WHERE c.status='pending' ORDER BY c.created_at DESC LIMIT 5");

$pageTitle  = 'Dashboard';
$pageSub    = 'PT Pesta Hijau Abadi — '.date('d F Y');
$activePage = 'dashboard';
include __DIR__.'/../../includes/header.php';
?>

<div class="stat-grid">
    <div class="stat-card green">
        <div class="stat-icon">👥</div>
        <div class="stat-label">Total Karyawan</div>
        <div class="stat-value"><?= (int)$sKar['total'] ?></div>
        <div class="stat-sub"><?= (int)$sKar['aktif'] ?> aktif · <?= (int)$sKar['cuti'] ?> cuti</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">✅</div>
        <div class="stat-label">Hadir Hari Ini</div>
        <div class="stat-value"><?= $hadir ?></div>
        <div class="stat-sub">dari <?= (int)$sKar['aktif'] ?> karyawan aktif</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-icon">⏳</div>
        <div class="stat-label">Pending Approval</div>
        <div class="stat-value"><?= $lemPend + $cutPend ?></div>
        <div class="stat-sub"><?= $lemPend ?> lembur · <?= $cutPend ?> cuti</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">💰</div>
        <div class="stat-label">Total Gaji <?= bulanNama($bulan) ?></div>
        <div class="stat-value" style="font-size:16px"><?= formatRp($totGaji) ?></div>
        <div class="stat-sub"><?= $tahun ?></div>
    </div>
</div>

<div class="grid-2 mb-2">
    <!-- Absensi Hari Ini -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Absensi Hari Ini</span>
            <a href="<?= BASE_URL ?>/pages/admin/absensi.php" class="btn btn-sm">Lihat semua</a>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Karyawan</th><th>Masuk</th><th>Keluar</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$absenHari || $absenHari->num_rows === 0): ?>
                <tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--text-m)">Belum ada absensi hari ini</td></tr>
                <?php else: while ($r = $absenHari->fetch_assoc()):
                    $bs = ['tepat'=>'badge-green','terlambat'=>'badge-amber','izin'=>'badge-blue','alpha'=>'badge-red'];
                    $bc = $bs[$r['status_masuk']] ?? 'badge-gray';
                    $label = $r['status_masuk'] === 'terlambat' ? 'Terlambat' : ucfirst($r['status_kehadiran']);
                ?>
                <tr>
                    <td>
                        <div class="name-cell">
                            <div class="avatar av-sm" style="background:<?= avatarBg((int)$r['user_id']) ?>"><?= initials($r['nama']) ?></div>
                            <div><div class="nc-name"><?= htmlspecialchars($r['nama']) ?></div>
                            <div class="nc-sub"><?= htmlspecialchars($r['nip']) ?></div></div>
                        </div>
                    </td>
                    <td class="mono text-sm"><?= $r['jam_masuk'] ? date('H:i', strtotime($r['jam_masuk'])) : '—' ?></td>
                    <td class="mono text-sm"><?= $r['jam_keluar'] ? date('H:i', strtotime($r['jam_keluar'])) : '—' ?></td>
                    <td><span class="badge <?= $bc ?>"><?= $label ?></span></td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Distribusi Dept -->
    <div class="card">
        <div class="card-header"><span class="card-title">Distribusi Karyawan</span></div>
        <div class="card-body">
        <?php $totalAktif = max(1,(int)$sKar['aktif']); while ($d = $deptDist->fetch_assoc()):
            $pct = round($d['jml'] / $totalAktif * 100); ?>
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px">
                    <span><?= htmlspecialchars($d['nama']) ?></span>
                    <span class="text-muted"><?= $d['jml'] ?> (<?= $pct ?>%)</span>
                </div>
                <div style="background:var(--surface-2);border-radius:100px;height:5px;overflow:hidden">
                    <div style="background:var(--green-600);height:100%;width:<?= $pct ?>%"></div>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    </div>
</div>

<?php if ($lemPend > 0 || $cutPend > 0): ?>
<div class="grid-2">
    <?php if ($lemPend > 0): ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">Lembur Pending</span>
            <a href="<?= BASE_URL ?>/pages/admin/lembur.php" class="btn btn-sm btn-amber">Kelola</a>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Karyawan</th><th>Tanggal</th><th>Durasi</th></tr></thead>
                <tbody>
                <?php while ($l = $lemList->fetch_assoc()): ?>
                <tr>
                    <td><div class="name-cell">
                        <div class="avatar av-sm" style="background:<?= avatarBg((int)$l['user_id']) ?>"><?= initials($l['nama']) ?></div>
                        <div class="nc-name"><?= htmlspecialchars($l['nama']) ?></div>
                    </div></td>
                    <td class="text-sm"><?= formatTgl($l['tanggal']) ?></td>
                    <td><span class="badge badge-amber"><?= round(($l['durasi_menit'] ?? 0)/60, 1) ?> jam</span></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($cutPend > 0): ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">Cuti Pending</span>
            <a href="<?= BASE_URL ?>/pages/admin/cuti.php" class="btn btn-sm btn-amber">Kelola</a>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Karyawan</th><th>Jenis</th><th>Lama</th></tr></thead>
                <tbody>
                <?php while ($c = $cutList->fetch_assoc()): ?>
                <tr>
                    <td><div class="name-cell">
                        <div class="avatar av-sm" style="background:<?= avatarBg((int)$c['user_id']) ?>"><?= initials($c['nama']) ?></div>
                        <div class="nc-name"><?= htmlspecialchars($c['nama']) ?></div>
                    </div></td>
                    <td class="text-sm"><?= htmlspecialchars($c['jenis_nama']) ?></td>
                    <td><span class="badge badge-blue"><?= (int)$c['jumlah_hari'] ?> hari</span></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__.'/../../includes/footer.php'; ?>
