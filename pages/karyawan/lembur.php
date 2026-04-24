<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireLogin();
$user = currentUser();
if ($user['role'] !== 'karyawan') redirect(BASE_URL.'/pages/admin/lembur.php');
$uid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tgl    = sanitize($_POST['tanggal']    ?? '');
    $mulai  = sanitize($_POST['jam_mulai']  ?? '');
    $selesai= sanitize($_POST['jam_selesai']?? '');
    $alasan = sanitize($_POST['alasan']     ?? '');

    if (!$tgl || !$mulai || !$selesai || !$alasan) {
        flash('error','Semua field wajib diisi.'); redirect(BASE_URL.'/pages/karyawan/lembur.php');
    }
    // Hitung durasi (support overnight)
    $ts1 = strtotime("2000-01-01 $mulai");
    $ts2 = strtotime("2000-01-01 $selesai");
    $menit = (int)(($ts2 - $ts1) / 60);
    if ($menit <= 0) $menit += 24 * 60; // overnight
    if ($menit < 30) {
        flash('error','Durasi lembur minimal 30 menit.'); redirect(BASE_URL.'/pages/karyawan/lembur.php');
    }
    // Cek duplikat
    $tgl_e = esc($tgl);
    $cek = db()->query("SELECT id FROM lembur WHERE user_id=$uid AND tanggal='$tgl_e' AND status!='ditolak'")->fetch_assoc();
    if ($cek) {
        flash('error','Sudah ada pengajuan lembur untuk tanggal tersebut.'); redirect(BASE_URL.'/pages/karyawan/lembur.php');
    }
    $mulai_e  = esc($mulai); $selesai_e = esc($selesai); $alasan_e = esc($alasan);
    db()->query("INSERT INTO lembur (user_id,tanggal,jam_mulai,jam_selesai,durasi_menit,alasan) VALUES ($uid,'$tgl_e','$mulai_e','$selesai_e',$menit,'$alasan_e')");
    if (db()->error) { flash('error','Gagal: '.db()->error); redirect(BASE_URL.'/pages/karyawan/lembur.php'); }

    // Notif HRD
    $nama_e = esc($user['nama']); $jam = round($menit/60,1); $tglFmt = esc(formatTgl($tgl));
    $hrdList = db()->query("SELECT id FROM users WHERE role IN ('admin','hrd')")->fetch_all(MYSQLI_ASSOC);
    foreach ($hrdList as $h) {
        $hid = (int)$h['id'];
        db()->query("INSERT INTO notifikasi (user_id,judul,pesan,tipe) VALUES ($hid,'Pengajuan Lembur Baru','$nama_e mengajukan lembur $tglFmt selama $jam jam','info')");
    }
    flash('success',"Pengajuan lembur $jam jam berhasil dikirim.");
    redirect(BASE_URL.'/pages/karyawan/lembur.php');
}

$page  = max(1,(int)($_GET['page']??1)); $per=10; $off=($page-1)*$per;
$total = (int)db()->query("SELECT COUNT(*) c FROM lembur WHERE user_id=$uid")->fetch_assoc()['c'];
$pages = max(1,(int)ceil($total/$per));
$rows  = db()->query("SELECT * FROM lembur WHERE user_id=$uid ORDER BY created_at DESC LIMIT $per OFFSET $off");
$rekap = db()->query("SELECT SUM(status='pending') pend,SUM(status='disetujui') dis,COALESCE(SUM(CASE WHEN status='disetujui' THEN durasi_menit ELSE 0 END),0) dm,COALESCE(SUM(CASE WHEN status='disetujui' THEN upah_lembur ELSE 0 END),0) upah FROM lembur WHERE user_id=$uid AND MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetch_assoc();

$pageTitle='Lembur Saya'; $activePage='lembur';
$topbarActions='<button class="btn btn-primary" onclick="openModal(\'mLembur\')">+ Ajukan Lembur</button>';
include __DIR__.'/../../includes/header.php';
?>

<div class="stat-grid mb-2" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card amber"><div class="stat-icon">⏳</div><div class="stat-label">Pending</div><div class="stat-value"><?=(int)$rekap['pend']?></div></div>
    <div class="stat-card green"><div class="stat-icon">✅</div><div class="stat-label">Disetujui</div><div class="stat-value"><?=(int)$rekap['dis']?></div></div>
    <div class="stat-card blue"><div class="stat-icon">⏱</div><div class="stat-label">Total Jam</div><div class="stat-value"><?=round($rekap['dm']/60,1)?></div><div class="stat-sub">bulan ini</div></div>
    <div class="stat-card green"><div class="stat-icon">💰</div><div class="stat-label">Upah Lembur</div><div class="stat-value" style="font-size:14px"><?=formatRp($rekap['upah'])?></div></div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Riwayat Pengajuan</span></div>
    <div class="tbl-wrap"><table>
        <thead><tr><th>Tanggal</th><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Alasan</th><th>Upah</th><th>Status</th><th>Catatan</th></tr></thead>
        <tbody>
        <?php if (!$rows||$rows->num_rows===0): ?>
        <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--text-m)">Belum ada pengajuan</td></tr>
        <?php else: while ($r=$rows->fetch_assoc()):
            $bs=['pending'=>'badge-amber','disetujui'=>'badge-green','ditolak'=>'badge-red'];?>
        <tr>
            <td class="text-sm"><?=formatTgl($r['tanggal'])?></td>
            <td class="mono text-sm"><?=substr($r['jam_mulai'],0,5)?></td>
            <td class="mono text-sm"><?=substr($r['jam_selesai'],0,5)?></td>
            <td><span class="badge badge-blue"><?=round($r['durasi_menit']/60,1)?> jam</span></td>
            <td class="text-sm text-muted" style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($r['alasan']??'')?></td>
            <td class="text-sm"><?=$r['upah_lembur']>0?formatRp($r['upah_lembur']):'—'?></td>
            <td><span class="badge <?=$bs[$r['status']]??'badge-gray'?>"><?=ucfirst($r['status'])?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($r['catatan_approver']??'—',0,30))?></td>
        </tr>
        <?php endwhile; endif;?>
        </tbody>
    </table></div>
    <div class="pagination">
        <span><?=$total?> pengajuan</span>
        <div class="page-btns">
            <?php if($page>1):?><a href="?page=<?=$page-1?>" class="page-btn">‹</a><?php endif;?>
            <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++):?><a href="?page=<?=$i?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a><?php endfor;?>
            <?php if($page<$pages):?><a href="?page=<?=$page+1?>" class="page-btn">›</a><?php endif;?>
        </div>
    </div>
</div>

<div class="modal-overlay" id="mLembur">
<div class="modal">
    <div class="modal-header"><span class="modal-title">Ajukan Lembur</span><button class="modal-close" onclick="closeModal('mLembur')">✕</button></div>
    <form method="POST">
    <div class="modal-body">
        <div class="form-grid">
            <div class="form-group form-full"><label class="form-label">Tanggal *</label>
                <input type="date" name="tanggal" class="form-control" required
                    min="<?=date('Y-m-d',strtotime('-30 days'))?>" max="<?=date('Y-m-d',strtotime('+7 days'))?>"></div>
            <div class="form-group"><label class="form-label">Jam Mulai *</label><input type="time" name="jam_mulai" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Jam Selesai *</label><input type="time" name="jam_selesai" class="form-control" required></div>
            <div class="form-group form-full"><label class="form-label">Alasan *</label>
                <textarea name="alasan" class="form-control" rows="3" required placeholder="Jelaskan pekerjaan saat lembur..."></textarea></div>
        </div>
        <div class="alert alert-info mt-1" style="font-size:12px">Upah: Gaji ÷ 173 × 1.5 × Jam (UU Ketenagakerjaan)</div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('mLembur')">Batal</button>
        <button type="submit" class="btn btn-primary">Kirim Pengajuan</button>
    </div>
    </form>
</div>
</div>
<?php include __DIR__.'/../../includes/footer.php'; ?>
