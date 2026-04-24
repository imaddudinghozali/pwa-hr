<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();

// ── POST: tambah / edit ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $nama   = sanitize($_POST['nama']        ?? '');
    $kode   = strtoupper(sanitize($_POST['kode'] ?? ''));
    $lat    = (float)($_POST['lokasi_lat']   ?? 0);
    $lng    = (float)($_POST['lokasi_lng']   ?? 0);
    $radius = (int)($_POST['radius_absen']   ?? 200);
    $kepala = sanitize($_POST['kepala']      ?? '');

    if (!$nama || !$kode) {
        flash('error', 'Nama dan Kode departemen wajib diisi.');
        redirect(BASE_URL.'/pages/admin/departemen.php');
    }

    $nama_e   = esc($nama);
    $kode_e   = esc($kode);
    $kepala_e = esc($kepala);

    if ($id > 0) {
        db()->query("UPDATE departemen SET
            nama='$nama_e', kode='$kode_e',
            lokasi_lat=$lat, lokasi_lng=$lng,
            radius_absen=$radius, kepala='$kepala_e'
            WHERE id=$id");
        if (db()->error) flash('error', 'Gagal update: '.db()->error);
        else flash('success', "Departemen $nama diperbarui.");
    } else {
        db()->query("INSERT INTO departemen (nama, kode, lokasi_lat, lokasi_lng, radius_absen, kepala)
            VALUES ('$nama_e', '$kode_e', $lat, $lng, $radius, '$kepala_e')");
        if (db()->error) flash('error', 'Gagal tambah: '.db()->error);
        else flash('success', "Departemen $nama ditambahkan.");
    }
    redirect(BASE_URL.'/pages/admin/departemen.php');
}

// ── DELETE ────────────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $r  = db()->query("SELECT nama FROM departemen WHERE id=$id")->fetch_assoc();
    if ($r) {
        db()->query("DELETE FROM departemen WHERE id=$id");
        flash('success', "Departemen {$r['nama']} dihapus.");
    }
    redirect(BASE_URL.'/pages/admin/departemen.php');
}

// ── LIST ──────────────────────────────────────────────────────
$rows = db()->query("SELECT d.*, COUNT(u.id) jml
    FROM departemen d
    LEFT JOIN users u ON u.departemen_id = d.id
    GROUP BY d.id ORDER BY d.nama");

$pageTitle     = 'Departemen';
$activePage    = 'departemen';
$topbarActions = '<button class="btn btn-primary" onclick="openModal(\'mDept\')">+ Tambah Departemen</button>';
include __DIR__.'/../../includes/header.php';
?>

<div class="alert alert-info mb-2" style="font-size:13px">
    📍 Koordinat GPS digunakan sebagai pusat validasi radius absensi per departemen.
</div>

<div class="card">
<div class="tbl-wrap">
<table>
    <thead><tr>
        <th>Nama</th><th>Kode</th><th>Kepala</th><th>Koordinat GPS</th><th>Radius</th><th>Karyawan</th><th style="width:110px">Aksi</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows || $rows->num_rows === 0): ?>
    <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--text-m)">Belum ada departemen</td></tr>
    <?php else: while ($r = $rows->fetch_assoc()): ?>
    <tr>
        <td style="font-weight:600"><?= htmlspecialchars($r['nama']) ?></td>
        <td>
            <code style="background:var(--surface-2);padding:2px 8px;border-radius:4px;font-size:12px">
                <?= htmlspecialchars($r['kode']) ?>
            </code>
        </td>
        <td class="text-sm"><?= htmlspecialchars($r['kepala'] ?? '—') ?></td>
        <td class="mono text-xs text-muted">
            <?= $r['lokasi_lat'] ? $r['lokasi_lat'].', '.$r['lokasi_lng'] : '—' ?>
        </td>
        <td class="text-sm"><?= (int)$r['radius_absen'] ?>m</td>
        <td class="text-sm"><?= (int)$r['jml'] ?> orang</td>
        <td>
            <div class="flex gap-2">
                <button class="btn btn-sm"
                    onclick='editDept(<?= json_encode($r, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>
                    Edit
                </button>
                <button class="btn btn-sm btn-danger"
                    onclick="confirmDel('<?= BASE_URL ?>/pages/admin/departemen.php?hapus=<?= $r['id'] ?>','<?= htmlspecialchars($r['nama'], ENT_QUOTES) ?>')">
                    ✕
                </button>
            </div>
        </td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>
</div>
</div>

<!-- Modal Tambah/Edit Departemen -->
<div class="modal-overlay" id="mDept">
<div class="modal">
    <div class="modal-header">
        <span class="modal-title" id="mDeptTitle">Tambah Departemen</span>
        <button class="modal-close" onclick="closeModal('mDept')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="id" id="dId" value="0">
    <div class="modal-body">
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Nama Departemen *</label>
                <input type="text" name="nama" id="dNama" class="form-control" required
                    placeholder="Contoh: Teknologi Informasi">
            </div>
            <div class="form-group">
                <label class="form-label">Kode *</label>
                <input type="text" name="kode" id="dKode" class="form-control" required
                    placeholder="TI, SDM, KEU..." maxlength="20">
            </div>
            <div class="form-group form-full">
                <label class="form-label">Kepala Departemen</label>
                <input type="text" name="kepala" id="dKepala" class="form-control"
                    placeholder="Nama kepala departemen (opsional)">
            </div>
            <div class="form-group">
                <label class="form-label">Latitude GPS</label>
                <input type="number" name="lokasi_lat" id="dLat" class="form-control"
                    step="0.000001" placeholder="-6.2088">
            </div>
            <div class="form-group">
                <label class="form-label">Longitude GPS</label>
                <input type="number" name="lokasi_lng" id="dLng" class="form-control"
                    step="0.000001" placeholder="106.8456">
            </div>
            <div class="form-group form-full">
                <label class="form-label">Radius Absensi (meter)</label>
                <input type="number" name="radius_absen" id="dRadius" class="form-control"
                    value="200" min="50" max="2000">
                <span class="form-hint">Karyawan harus dalam radius ini dari koordinat GPS saat absensi</span>
            </div>
        </div>
        <div class="alert alert-info mt-1" style="font-size:12px">
            💡 Cara dapat koordinat: buka Google Maps → klik kanan lokasi kantor → salin angka koordinat
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('mDept')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
    </div>
    </form>
</div>
</div>

<script>
function editDept(d) {
    document.getElementById('dId').value     = d.id;
    document.getElementById('dNama').value   = d.nama || '';
    document.getElementById('dKode').value   = d.kode || '';
    document.getElementById('dKepala').value = d.kepala || '';
    document.getElementById('dLat').value    = d.lokasi_lat || '';
    document.getElementById('dLng').value    = d.lokasi_lng || '';
    document.getElementById('dRadius').value = d.radius_absen || 200;
    document.getElementById('mDeptTitle').textContent = 'Edit Departemen';
    openModal('mDept');
}
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
