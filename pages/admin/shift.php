<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)$_POST['id'];
    $nama   = sanitize($_POST['nama']    ?? '');
    $masuk  = sanitize($_POST['jam_masuk']  ?? '');
    $keluar = sanitize($_POST['jam_keluar'] ?? '');
    $tol    = (int)($_POST['toleransi_terlambat'] ?? 15);
    $warna  = sanitize($_POST['warna'] ?? '#22c55e');

    if ($id > 0) {
        $stmt = dbPrepare("UPDATE shift SET nama=?,jam_masuk=?,jam_keluar=?,toleransi_terlambat=?,warna=? WHERE id=?");
        $stmt->bind_param('sssisi',$nama,$masuk,$keluar,$tol,$warna,$id);
        $stmt->execute(); $stmt->close();
        flash('success',"Shift $nama diperbarui.");
    } else {
        $stmt = dbPrepare("INSERT INTO shift (nama,jam_masuk,jam_keluar,toleransi_terlambat,warna) VALUES (?,?,?,?,?)");
        $stmt->bind_param('sssis',$nama,$masuk,$keluar,$tol,$warna);
        $stmt->execute(); $stmt->close();
        flash('success',"Shift $nama ditambahkan.");
    }
    redirect(BASE_URL.'/pages/admin/shift.php');
}
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $r  = db()->query("SELECT nama FROM shift WHERE id=$id")->fetch_assoc();
    if ($r) { db()->query("DELETE FROM shift WHERE id=$id"); flash('success',"Shift {$r['nama']} dihapus."); }
    redirect(BASE_URL.'/pages/admin/shift.php');
}

$rows = db()->query("SELECT s.*,COUNT(u.id) jml FROM shift s LEFT JOIN users u ON u.shift_id=s.id GROUP BY s.id ORDER BY s.nama");
$pageTitle = 'Shift Kerja'; $activePage = 'shift';
$topbarActions = '<button class="btn btn-primary" onclick="openModal(\'mShift\')">+ Tambah Shift</button>';
include __DIR__.'/../../includes/header.php';
?>
<div class="card">
<div class="tbl-wrap">
<table>
    <thead><tr><th>Nama Shift</th><th>Masuk</th><th>Keluar</th><th>Toleransi</th><th>Karyawan</th><th>Warna</th><th style="width:100px">Aksi</th></tr></thead>
    <tbody>
    <?php while ($r = $rows->fetch_assoc()): ?>
    <tr>
        <td style="font-weight:600"><?=htmlspecialchars($r['nama'])?></td>
        <td class="mono"><?=substr($r['jam_masuk'],0,5)?></td>
        <td class="mono"><?=substr($r['jam_keluar'],0,5)?></td>
        <td><?=$r['toleransi_terlambat']?> menit</td>
        <td><?=$r['jml']?> orang</td>
        <td><span style="display:inline-flex;align-items:center;gap:6px"><span style="width:18px;height:18px;background:<?=htmlspecialchars($r['warna'])?>;border-radius:4px;display:inline-block"></span><span class="mono text-xs"><?=htmlspecialchars($r['warna'])?></span></span></td>
        <td><div class="flex gap-2">
            <button class="btn btn-sm" onclick='editShift(<?=json_encode($r,JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP)?>)'>Edit</button>
            <button class="btn btn-sm btn-danger" onclick="confirmDel('<?=BASE_URL?>/pages/admin/shift.php?hapus=<?=$r['id']?>','<?=htmlspecialchars($r['nama'],ENT_QUOTES)?>')">✕</button>
        </div></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>
</div>
<div class="modal-overlay" id="mShift">
<div class="modal">
    <div class="modal-header"><span class="modal-title" id="mShiftTitle">Tambah Shift</span><button class="modal-close" onclick="closeModal('mShift')">✕</button></div>
    <form method="POST"><input type="hidden" name="id" id="sId" value="0">
    <div class="modal-body"><div class="form-grid">
        <div class="form-group form-full"><label class="form-label">Nama Shift *</label><input type="text" name="nama" id="sNama" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Jam Masuk *</label><input type="time" name="jam_masuk" id="sMasuk" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Jam Keluar *</label><input type="time" name="jam_keluar" id="sKeluar" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Toleransi Terlambat (menit)</label><input type="number" name="toleransi_terlambat" id="sTol" class="form-control" value="15" min="0" max="120"></div>
        <div class="form-group"><label class="form-label">Warna</label><input type="color" name="warna" id="sWarna" class="form-control" value="#22c55e" style="height:40px;cursor:pointer"></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn" onclick="closeModal('mShift')">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
    </form>
</div>
</div>
<script>
function editShift(d){
    document.getElementById('sId').value=d.id; document.getElementById('sNama').value=d.nama;
    document.getElementById('sMasuk').value=d.jam_masuk; document.getElementById('sKeluar').value=d.jam_keluar;
    document.getElementById('sTol').value=d.toleransi_terlambat; document.getElementById('sWarna').value=d.warna;
    document.getElementById('mShiftTitle').textContent='Edit Shift'; openModal('mShift');
}
</script>
<?php include __DIR__.'/../../includes/footer.php'; ?>
