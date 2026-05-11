<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();

$hari  = date('Y-m-d');
$bulan = (int)date('m');
$tahun = (int)date('Y');

function dashQuery(string $sql): mysqli_result|false {
    return db()->query($sql);
}

function dashRow(string $sql, array $fallback = []): array {
    $res = dashQuery($sql);
    if (!$res) return $fallback;
    $row = $res->fetch_assoc();
    return $row ?: $fallback;
}

function dashInt(string $sql, string $key = 'c'): int {
    $row = dashRow($sql, [$key => 0]);
    return (int)($row[$key] ?? 0);
}

function dashFloat(string $sql, string $key = 't'): float {
    $row = dashRow($sql, [$key => 0]);
    return (float)($row[$key] ?? 0);
}

// Stats karyawan
$sKar = dashRow("SELECT
    COUNT(*) total,
    SUM(status='aktif') aktif,
    SUM(status='cuti') cuti
    FROM users WHERE role='karyawan'", ['total' => 0, 'aktif' => 0, 'cuti' => 0]);

// Hadir hari ini
$hadir = dashInt("SELECT COUNT(DISTINCT user_id) c FROM absensi
    WHERE tanggal='$hari' AND status_kehadiran='hadir'");

// Pending
$lemPend = dashInt("SELECT COUNT(*) c FROM lembur WHERE status='pending'");
$cutPend = dashInt("SELECT COUNT(*) c FROM pengajuan_cuti WHERE status='pending'");

// Total gaji bulan ini
$totGaji = dashFloat("SELECT COALESCE(SUM(gaji_bersih),0) t FROM slip_gaji
    WHERE bulan=$bulan AND tahun=$tahun");

// Absensi hari ini
$absenHari = dashQuery("SELECT a.*,u.nama,u.nip,d.nama dept_nama
    FROM absensi a
    JOIN users u ON a.user_id=u.id
    LEFT JOIN departemen d ON u.departemen_id=d.id
    WHERE a.tanggal='$hari'
    ORDER BY a.jam_masuk DESC LIMIT 10");

// Distribusi departemen
$deptDist = dashQuery("SELECT d.nama,COUNT(u.id) jml
    FROM departemen d
    LEFT JOIN users u ON u.departemen_id=d.id AND u.role='karyawan' AND u.status='aktif'
    GROUP BY d.id,d.nama ORDER BY jml DESC");

// Pending lembur (5)
$lemList = dashQuery("SELECT l.*,u.nama,u.nip FROM lembur l
    JOIN users u ON l.user_id=u.id WHERE l.status='pending'
    ORDER BY l.created_at DESC LIMIT 5");

// Pending cuti (5)
$cutList = dashQuery("SELECT c.*,u.nama,jc.nama jenis_nama FROM pengajuan_cuti c
    JOIN users u ON c.user_id=u.id
    JOIN jenis_cuti jc ON c.jenis_cuti_id=jc.id
    WHERE c.status='pending' ORDER BY c.created_at DESC LIMIT 5");

// Kontrak akan habis dalam 30 hari
$kontrakHabis = dashQuery("SELECT u.nama, u.nip, u.jenis_karyawan,
    u.tanggal_kontrak_selesai,
    DATEDIFF(u.tanggal_kontrak_selesai, CURDATE()) sisa_hari
    FROM users u WHERE u.jenis_karyawan IN ('kontrak','magang')
    AND u.tanggal_kontrak_selesai BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)
    AND u.status='aktif' ORDER BY u.tanggal_kontrak_selesai ASC LIMIT 10");

// Reimburse pending
$reimbPend = dashInt("SELECT COUNT(*) c FROM reimburse WHERE status='pending'");

$pageTitle  = 'Dashboard';
$pageSub    = 'PT Pesta Hijau Abadi - '.date('d F Y');
$activePage = 'dashboard';
include __DIR__.'/../../includes/header.php';
?>

<div class="dash-hero">
    <div class="dash-panel">
        <div class="dash-eyebrow">Ringkasan operasional</div>
        <div class="dash-heading">Pantau absensi, approval, dan payroll dari satu tempat.</div>
        <div class="dash-sub"><?= (int)$sKar['aktif'] ?> karyawan aktif &middot; <?= $hadir ?> hadir hari ini &middot; <?= $lemPend + $cutPend + $reimbPend ?> approval menunggu</div>
    </div>
    <div class="dash-panel">
        <div class="dash-eyebrow">Aksi cepat</div>
        <div class="quick-action-grid mt-1">
            <a class="quick-action" href="<?= BASE_URL ?>/pages/admin/absensi.php"><span class="quick-action-icon">&#9673;</span><span>Rekap Absensi</span></a>
            <a class="quick-action" href="<?= BASE_URL ?>/pages/admin/karyawan.php"><span class="quick-action-icon">&#9783;</span><span>Data Karyawan</span></a>
            <a class="quick-action" href="<?= BASE_URL ?>/pages/admin/reimburse.php"><span class="quick-action-icon">&#9674;</span><span>Reimburse</span></a>
            <a class="quick-action" href="<?= BASE_URL ?>/pages/admin/penggajian.php"><span class="quick-action-icon">Rp</span><span>Penggajian</span></a>
        </div>
    </div>
</div>

<?php if ($kontrakHabis && $kontrakHabis->num_rows > 0): ?>
<div class="alert alert-amber" style="display:flex;align-items:flex-start;gap:12px;margin-bottom:1rem">
    <span style="font-size:22px;flex-shrink:0">!</span>
    <div style="flex:1">
        <div style="font-weight:700;margin-bottom:6px">Kontrak / Magang Akan Berakhir dalam 30 Hari</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php while ($k = $kontrakHabis->fetch_assoc()): ?>
        <span style="background:rgba(245,158,11,.2);padding:3px 10px;border-radius:100px;font-size:12px">
            <strong><?= htmlspecialchars($k['nama']) ?></strong>
            (<?= ucfirst($k['jenis_karyawan']) ?>) - <?= (int)$k['sisa_hari'] ?> hari lagi
            <span class="text-muted"><?= formatTgl($k['tanggal_kontrak_selesai']) ?></span>
        </span>
        <?php endwhile; ?>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/pages/admin/karyawan.php?jenis=kontrak" class="btn btn-sm btn-amber" style="flex-shrink:0">Kelola</a>
</div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card green">
        <div class="stat-icon">&#9783;</div>
        <div class="stat-label">Total Karyawan</div>
        <div class="stat-value"><?= (int)$sKar['total'] ?></div>
        <div class="stat-sub"><?= (int)$sKar['aktif'] ?> aktif &middot; <?= (int)$sKar['cuti'] ?> cuti</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">&#10003;</div>
        <div class="stat-label">Hadir Hari Ini</div>
        <div class="stat-value"><?= $hadir ?></div>
        <div class="stat-sub">dari <?= (int)$sKar['aktif'] ?> karyawan aktif</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-icon">&#8987;</div>
        <div class="stat-label">Pending Approval</div>
        <div class="stat-value"><?= $lemPend + $cutPend + $reimbPend ?></div>
        <div class="stat-sub"><?= $lemPend ?> lembur &middot; <?= $cutPend ?> cuti &middot; <?= $reimbPend ?> reimburse</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">Rp</div>
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
                    <td class="mono text-sm"><?= $r['jam_masuk'] ? date('H:i', strtotime($r['jam_masuk'])) : '-' ?></td>
                    <td class="mono text-sm"><?= $r['jam_keluar'] ? date('H:i', strtotime($r['jam_keluar'])) : '-' ?></td>
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
        <?php if (!$deptDist || $deptDist->num_rows === 0): ?>
            <div class="empty-state">Belum ada data departemen</div>
        <?php else: $totalAktif = max(1,(int)$sKar['aktif']); while ($d = $deptDist->fetch_assoc()):
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
        <?php endwhile; endif; ?>
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
                <?php if (!$lemList || $lemList->num_rows === 0): ?>
                <tr><td colspan="3" style="text-align:center;padding:1.5rem;color:var(--text-m)">Tidak ada lembur pending</td></tr>
                <?php else: while ($l = $lemList->fetch_assoc()): ?>
                <tr>
                    <td><div class="name-cell">
                        <div class="avatar av-sm" style="background:<?= avatarBg((int)$l['user_id']) ?>"><?= initials($l['nama']) ?></div>
                        <div class="nc-name"><?= htmlspecialchars($l['nama']) ?></div>
                    </div></td>
                    <td class="text-sm"><?= formatTgl($l['tanggal']) ?></td>
                    <td><span class="badge badge-amber"><?= round(($l['durasi_menit'] ?? 0)/60, 1) ?> jam</span></td>
                </tr>
                <?php endwhile; endif; ?>
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
                <?php if (!$cutList || $cutList->num_rows === 0): ?>
                <tr><td colspan="3" style="text-align:center;padding:1.5rem;color:var(--text-m)">Tidak ada cuti pending</td></tr>
                <?php else: while ($c = $cutList->fetch_assoc()): ?>
                <tr>
                    <td><div class="name-cell">
                        <div class="avatar av-sm" style="background:<?= avatarBg((int)$c['user_id']) ?>"><?= initials($c['nama']) ?></div>
                        <div class="nc-name"><?= htmlspecialchars($c['nama']) ?></div>
                    </div></td>
                    <td class="text-sm"><?= htmlspecialchars($c['jenis_nama']) ?></td>
                    <td><span class="badge badge-blue"><?= (int)$c['jumlah_hari'] ?> hari</span></td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__.'/../../includes/footer.php'; ?>
