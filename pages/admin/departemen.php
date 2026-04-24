<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)$_POST['id'];
    $nama   = sanitize($_POST['nama']   ?? '');
    $kode   = strtoupper(sanitize($_POST['kode'] ?? ''));
    $lat    = (float)($_POST['lokasi_lat']  ?? 0);
    $lng    = (float)($_POST['lokasi_lng']  ?? 0);
    $radius = (int)($_POST['radius_absen'] ?? 200);
    $kepala = sanitize($_POST['kepala'] ?? '');

    if ($id > 0) {
        $stmt = dbPrepare("UPDATE departemen SET nama=?,kode=?,lokasi_lat=?,lokasi_lng=?,radius_absen=?,kepala=? WHERE id=?");
        $stmt->bind_param('ssddssi',$nama,$kode,$lat,$lng,$radius,$kepala,$id);
        $stmt->execute(); $stmt->close();
        flash('success',"Departemen $nama diperbarui.");
    } else {
        $stmt = dbPrepare("INSERT INTO departemen (nama,kode,lokasi_lat,lokasi_lng,radius_absen,kepala) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssddis',$nama,$kode,$lat,$lng,$radius,$kepala);
        $stmt->execute(); $stmt->close();
        flash('success',"Departemen $nama ditambahkan.");
    }
    redirect(BASE_URL.'/pages/admin/departemen.php');
}
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $r  = db()->query("SELECT nama FROM departemen WHERE id=$id")->fetch_assoc();
    if ($r) { db()->query("DELETE FROM departemen WHERE id=$id"); flash('success',"Departemen {$r['nama']} dihapus."); }
    redirect(BASE_URL.'/pages/admin/departemen.php');
}

$rows = db()->query("SELECT d.*,COUNT(u.id) jml FROM departemen d LEFT JOIN users u ON u.departemen_id=d.id GROUP BY d.id ORDER BY d.nama");
$pageTitle = 'Departemen'; $activePage = 'departemen';
$topbarActions = '<button class="btn btn-primary" onclick="openModal(\'mDept\')">+ Tambah Departemen</button>';
include __DIR__.'/../../includes/header.php';
?>
<div class="alert alert-info mb-2">📍 Koordinat GPS digunakan sebagai pusat validasi absensi per departemen.</div>
<div class="card">
<div class="tbl-wrap">
<table>
    <thead><tr><th>Nama</th><th>Kode</th><th>Koordinat GPS</th><th>Radius</th><th>Kepala</th><th>Karyawan</th><th style="width:100px">Aksi</th></tr></thead>
    <tbody>
    <?php while ($r = $rows->fetch_assoc()): ?>
    <tr>
        <td style="font-weight:600"><?=htmlspecialchars($r['nama'])?></td>
        <td><code style="background:var(--surface-2);padding:2px 8px;border-radius:4px;font-size:12px"><?=$r['kode']?></code></td>
        <td class="mono text-xs text-muted"><?=$r['lokasi_lat'] ? $r['lokasi_lat'].', '.$r['lokasi_lng'] : '—'?></td>
        <td class="text-sm"><?=$r['radius_absen']?>m</td>
        <td class="text-sm"><?=htmlspecialchars($r['kepala']??'—')?></td>
        <td><?=$r['jml']?> orang</td>
        <td><div class="flex gap-2">
            <button class="btn btn-sm" onclick='editDept(<?=json_encode($r,JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP)?>)'>Edit</button>
            <button class="btn btn-sm btn-danger" onclick="confirmDel('<?=BASE_URL?>/pages/admin/departemen.php?hapus=<?=$r['id']?>','<?=htmlspecialchars($r['nama'],ENT_QUOTES)?>')">✕</button>
        </div></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>
</div>
<div class="modal-overlay" id="mDept">
<div class="modal">
    <div class="modal-header"><span class="modal-title" id="mDeptTitle">Tambah Departemen</span><button class="modal-close" onclick="closeModal('mDept')">✕</button></div>
    <form method="POST"><input type="hidden" name="id" id="dId" value="0">
    <div class="modal-body"><div class="form-grid">
        <div class="form-group"><label class="form-label">Nama *</label><input type="text" name="nama" id="dNama" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Kode *</label><input type="text" name="kode" id="dKode" class="form-control" required placeholder="TI, SDM..."></div>
        <div class="form-group"><label class="form-label">Kepala Departemen</label><input type="text" name="kepala" id="dKepala" class="form-control"></div>
        <div class="form-group"><label class="form-label">Radius Absensi (m)</label><input type="number" name="radius_absen" id="dRadius" class="form-control" value="200" min="50"></div>
        <div class="form-group"><label class="form-label">Latitude GPS</label><input type="number" name="lokasi_lat" id="dLat" class="form-control" step="0.000001"></div>
        <div class="form-group"><label class="form-label">Longitude GPS</label><input type="number" name="lokasi_lng" id="dLng" class="form-control" step="0.000001"></div>
    </div>
    <div class="alert alert-info mt-1" style="font-size:12px">💡 Google Maps → klik kanan lokasi kantor → salin koordinat</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn" onclick="closeModal('mDept')">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
    </form>
</div>
</div>
<script>
function editDept(d){
    document.getElementById('dId').value=d.id; document.getElementById('dNama').value=d.nama;
    document.getElementById('dKode').value=d.kode; document.getElementById('dKepala').value=d.kepala||'';
    document.getElementById('dRadius').value=d.radius_absen||200;
    document.getElementById('dLat').value=d.lokasi_lat||''; document.getElementById('dLng').value=d.lokasi_lng||'';
    document.getElementById('mDeptTitle').textContent='Edit Departemen'; openModal('mDept');
}
</script>
<?php include __DIR__.'/../../includes/footer.php'; ?>
