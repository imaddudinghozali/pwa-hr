<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireLogin();
$user = currentUser();
if ($user['role'] !== 'karyawan') redirect(BASE_URL.'/pages/admin/cuti.php');
$uid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis   = (int)$_POST['jenis_cuti_id'];
    $mulai   = sanitize($_POST['tanggal_mulai']  ?? '');
    $selesai = sanitize($_POST['tanggal_selesai']?? '');
    $alasan  = sanitize($_POST['alasan']         ?? '');
    $hari    = (int)$_POST['jumlah_hari'];

    if (!$jenis||!$mulai||!$selesai||!$alasan||$hari<1) {
        flash('error','Lengkapi semua field.'); redirect(BASE_URL.'/pages/karyawan/cuti.php');
    }
    $jc = db()->query("SELECT * FROM jenis_cuti WHERE id=$jenis")->fetch_assoc();
    if ($jc && $jc['nama']==='Cuti Tahunan' && $hari>(int)$user['sisa_cuti']) {
        flash('error',"Sisa cuti Anda hanya {$user['sisa_cuti']} hari."); redirect(BASE_URL.'/pages/karyawan/cuti.php');
    }
    $mulai_e=esc($mulai); $selesai_e=esc($selesai);
    $cek = db()->query("SELECT id FROM pengajuan_cuti WHERE user_id=$uid AND status!='ditolak' AND tanggal_mulai<='$selesai_e' AND tanggal_selesai>='$mulai_e'")->fetch_assoc();
    if ($cek) { flash('error','Tumpang tindih dengan cuti yang sudah ada.'); redirect(BASE_URL.'/pages/karyawan/cuti.php'); }

    $alasan_e=esc($alasan);
    db()->query("INSERT INTO pengajuan_cuti (user_id,jenis_cuti_id,tanggal_mulai,tanggal_selesai,jumlah_hari,alasan) VALUES ($uid,$jenis,'$mulai_e','$selesai_e',$hari,'$alasan_e')");

    $nama_e=esc($user['nama']); $jNama_e=esc($jc['nama']??'');
    $hrdList=db()->query("SELECT id FROM users WHERE role IN ('admin','hrd')")->fetch_all(MYSQLI_ASSOC);
    foreach ($hrdList as $h) { $hid=(int)$h['id']; db()->query("INSERT INTO notifikasi (user_id,judul,pesan,tipe) VALUES ($hid,'Pengajuan Cuti Baru','$nama_e mengajukan $jNama_e selama $hari hari','info')"); }
    flash('success','Pengajuan cuti terkirim. Menunggu persetujuan HRD.');
    redirect(BASE_URL.'/pages/karyawan/cuti.php');
}

$page  = max(1,(int)($_GET['page']??1)); $per=10; $off=($page-1)*$per;
$total = (int)db()->query("SELECT COUNT(*) c FROM pengajuan_cuti WHERE user_id=$uid")->fetch_assoc()['c'];
$pages = max(1,(int)ceil($total/$per));
$rows  = db()->query("SELECT c.*,jc.nama jenis_nama FROM pengajuan_cuti c JOIN jenis_cuti jc ON c.jenis_cuti_id=jc.id WHERE c.user_id=$uid ORDER BY c.created_at DESC LIMIT $per OFFSET $off");
$jenisList = db()->query("SELECT * FROM jenis_cuti ORDER BY nama")->fetch_all(MYSQLI_ASSOC);

$pageTitle='Pengajuan Cuti'; $activePage='cuti';
$topbarActions='<button class="btn btn-primary" onclick="openModal(\'mCuti\')">+ Ajukan Cuti</button>';
include __DIR__.'/../../includes/header.php';
?>

<div class="card mb-2" style="background:linear-gradient(135deg,rgba(34,197,94,.08),rgba(34,197,94,.03));border-color:var(--border-md)">
    <div class="card-body" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
        <div style="text-align:center">
            <div style="font-size:40px;font-weight:800;color:var(--green-400)"><?=(int)$user['sisa_cuti']?></div>
            <div class="text-sm text-muted">Sisa Cuti Tahunan</div>
        </div>
        <div style="flex:1;min-width:180px">
            <?php $used=12-(int)$user['sisa_cuti']; $pct=min(100,round($used/12*100)); ?>
            <div class="text-sm text-muted mb-1">Penggunaan cuti tahunan: <?=$used?>/12 hari</div>
            <div style="background:var(--surface-2);border-radius:100px;height:8px;overflow:hidden">
                <div style="background:var(--green-600);height:100%;width:<?=$pct?>%"></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Riwayat Pengajuan Cuti</span></div>
    <div class="tbl-wrap"><table>
        <thead><tr><th>Jenis</th><th>Mulai</th><th>Selesai</th><th>Lama</th><th>Alasan</th><th>Status</th><th>Catatan</th></tr></thead>
        <tbody>
        <?php if (!$rows||$rows->num_rows===0): ?>
        <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--text-m)">Belum ada pengajuan</td></tr>
        <?php else: while ($r=$rows->fetch_assoc()):
            $bs=['pending'=>'badge-amber','disetujui'=>'badge-green','ditolak'=>'badge-red'];?>
        <tr>
            <td><span class="badge badge-blue"><?=htmlspecialchars($r['jenis_nama'])?></span></td>
            <td class="text-sm"><?=formatTgl($r['tanggal_mulai'])?></td>
            <td class="text-sm"><?=formatTgl($r['tanggal_selesai'])?></td>
            <td><span class="badge badge-purple"><?=(int)$r['jumlah_hari']?> hari</span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($r['alasan']??'',0,40))?></td>
            <td><span class="badge <?=$bs[$r['status']]??'badge-gray'?>"><?=ucfirst($r['status'])?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($r['catatan_approver']??'—',0,35))?></td>
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

<div class="modal-overlay" id="mCuti">
<div class="modal">
    <div class="modal-header"><span class="modal-title">Ajukan Cuti</span><button class="modal-close" onclick="closeModal('mCuti')">✕</button></div>
    <form method="POST"><input type="hidden" name="jumlah_hari" id="hidden-hari" value="0">
    <div class="modal-body">
        <div class="form-grid">
            <div class="form-group form-full"><label class="form-label">Jenis Cuti *</label>
                <select name="jenis_cuti_id" class="form-control" required>
                    <option value="">— Pilih jenis —</option>
                    <?php foreach ($jenisList as $j): ?><option value="<?=$j['id']?>"><?=htmlspecialchars($j['nama'])?> (maks <?=$j['max_hari']?> hari)</option><?php endforeach;?>
                </select></div>
            <div class="form-group"><label class="form-label">Tanggal Mulai *</label>
                <input type="date" name="tanggal_mulai" id="cuti-mulai" class="form-control" required min="<?=date('Y-m-d')?>"></div>
            <div class="form-group"><label class="form-label">Tanggal Selesai *</label>
                <input type="date" name="tanggal_selesai" id="cuti-selesai" class="form-control" required min="<?=date('Y-m-d')?>"></div>
            <div class="form-group form-full">
                <div style="padding:9px 12px;background:var(--surface-3);border-radius:var(--r);font-size:13px;color:var(--green-400);font-weight:700" id="jumlah-hari">— pilih tanggal —</div>
            </div>
            <div class="form-group form-full"><label class="form-label">Alasan *</label>
                <textarea name="alasan" class="form-control" rows="3" required></textarea></div>
        </div>
        <div class="alert alert-info mt-1" style="font-size:12px">Sisa cuti tahunan: <strong><?=(int)$user['sisa_cuti']?> hari</strong></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('mCuti')">Batal</button>
        <button type="submit" class="btn btn-primary">Kirim</button>
    </div>
    </form>
</div>
</div>
<?php include __DIR__.'/../../includes/footer.php'; ?>
