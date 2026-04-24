<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { redirect(BASE_URL.'/pages/admin/karyawan.php'); }

$usr = db()->query("SELECT u.*,d.nama dept_nama,j.nama jabatan_nama,j.gaji_pokok,j.tunjangan_jabatan,s.nama shift_nama,s.jam_masuk,s.jam_keluar
    FROM users u
    LEFT JOIN departemen d ON u.departemen_id=d.id
    LEFT JOIN jabatan j ON u.jabatan_id=j.id
    LEFT JOIN shift s ON u.shift_id=s.id
    WHERE u.id=$id LIMIT 1")->fetch_assoc();
if (!$usr) { redirect(BASE_URL.'/pages/admin/karyawan.php'); }

$bulan = (int)date('m'); $tahun = (int)date('Y');
$rek   = db()->query("SELECT SUM(status_kehadiran='hadir') hadir, SUM(status_kehadiran='alpha') alpha, SUM(status_masuk='terlambat') terlambat FROM absensi WHERE user_id=$id AND MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun")->fetch_assoc();
$lemJam = (float)db()->query("SELECT COALESCE(SUM(durasi_menit),0)/60 j FROM lembur WHERE user_id=$id AND MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun AND status='disetujui'")->fetch_assoc()['j'];
$slips  = db()->query("SELECT bulan,tahun,gaji_bersih,status FROM slip_gaji WHERE user_id=$id ORDER BY tahun DESC,bulan DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
$absen10= db()->query("SELECT * FROM absensi WHERE user_id=$id ORDER BY tanggal DESC LIMIT 10");

$pageTitle  = htmlspecialchars($usr['nama']);
$pageSub    = 'Detail Karyawan';
$activePage = 'karyawan';
$topbarActions = '<a href="'.BASE_URL.'/pages/admin/karyawan.php" class="btn btn-sm">← Kembali</a>';
include __DIR__.'/../../includes/header.php';
?>

<div class="grid-2" style="grid-template-columns:300px 1fr;align-items:start">
    <div>
        <div class="card mb-2" style="text-align:center">
            <div class="card-body">
                <div class="avatar av-xl" style="background:<?=avatarBg($id)?>;margin:0 auto 12px"><?=initials($usr['nama'])?></div>
                <div style="font-size:18px;font-weight:700"><?=htmlspecialchars($usr['nama'])?></div>
                <div class="mono text-sm text-muted"><?=$usr['nip']?></div>
                <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin:10px 0">
                    <?php $bs=['aktif'=>'badge-green','nonaktif'=>'badge-gray','cuti'=>'badge-amber'];?>
                    <span class="badge <?=$bs[$usr['status']]??'badge-gray'?>"><?=ucfirst($usr['status'])?></span>
                    <span class="badge badge-purple"><?=strtoupper($usr['role'])?></span>
                </div>
                <table style="width:100%;text-align:left;font-size:13px">
                    <?php $info=[['Email',$usr['email']??'—'],['Telp',$usr['telepon']??'—'],['Dept',$usr['dept_nama']??'—'],['Jabatan',$usr['jabatan_nama']??'—'],['Shift',$usr['shift_nama']??'—'],['Bergabung',formatTgl($usr['tanggal_bergabung']??'')],['Gaji Pokok',formatRp($usr['gaji_pokok']??0)],['Sisa Cuti',$usr['sisa_cuti'].' hari']];
                    foreach ($info as [$k,$v]): ?>
                    <tr><td style="color:var(--text-m);padding:5px 0;font-size:12px"><?=$k?></td><td style="padding:5px 0"><?=htmlspecialchars($v)?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <div>
        <div class="stat-grid mb-2" style="grid-template-columns:repeat(4,1fr)">
            <div class="stat-card green"><div class="stat-label">Hadir</div><div class="stat-value"><?=(int)$rek['hadir']?></div></div>
            <div class="stat-card amber"><div class="stat-label">Terlambat</div><div class="stat-value"><?=(int)$rek['terlambat']?></div></div>
            <div class="stat-card red"><div class="stat-label">Alpha</div><div class="stat-value"><?=(int)$rek['alpha']?></div></div>
            <div class="stat-card blue"><div class="stat-label">Jam Lembur</div><div class="stat-value"><?=round($lemJam,1)?></div></div>
        </div>
        <?php if (!empty($slips)): ?>
        <div class="card mb-2">
            <div class="card-header"><span class="card-title">Riwayat Slip Gaji</span><a href="<?=BASE_URL?>/pages/admin/penggajian.php" class="btn btn-sm">Kelola</a></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>Periode</th><th>Gaji Bersih</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($slips as $s): $bsSlip=['draft'=>'badge-amber','final'=>'badge-blue','dibayar'=>'badge-green']; ?>
                <tr><td><?=bulanNama($s['bulan']).' '.$s['tahun']?></td><td class="mono text-sm"><?=formatRp($s['gaji_bersih'])?></td><td><span class="badge <?=$bsSlip[$s['status']]??'badge-gray'?>"><?=ucfirst($s['status'])?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header"><span class="card-title">10 Absensi Terbaru</span></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>Tanggal</th><th>Masuk</th><th>Keluar</th><th>GPS</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$absen10 || $absen10->num_rows===0): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text-m);padding:1.5rem">Belum ada absensi</td></tr>
                <?php else: while ($a=$absen10->fetch_assoc()):
                    $bs=['tepat'=>'badge-green','terlambat'=>'badge-amber','alpha'=>'badge-red','izin'=>'badge-blue'];?>
                <tr>
                    <td class="text-sm"><?=formatTgl($a['tanggal'])?></td>
                    <td class="mono text-sm"><?=$a['jam_masuk']?date('H:i',strtotime($a['jam_masuk'])):'—'?></td>
                    <td class="mono text-sm"><?=$a['jam_keluar']?date('H:i',strtotime($a['jam_keluar'])):'—'?></td>
                    <td class="text-sm text-muted"><?=$a['jarak_masuk']?$a['jarak_masuk'].'m':'—'?></td>
                    <td><span class="badge <?=$bs[$a['status_masuk']]??'badge-gray'?>"><?=ucfirst($a['status_masuk'])?></span></td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>
<?php include __DIR__.'/../../includes/footer.php'; ?>
