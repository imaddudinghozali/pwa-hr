<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireLogin();
$user = currentUser();
if ($user['role'] !== 'karyawan') redirect(BASE_URL.'/pages/admin/reimburse.php');

$uid = (int)$user['id'];

$uploadDir = __DIR__.'/../../assets/uploads/reimburse/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ── POST: Submit reimburse ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reimb'])) {
    $tanggal    = sanitize($_POST['tanggal']    ?? '');
    $kategori   = sanitize($_POST['kategori']   ?? '');
    $jumlah     = (float)($_POST['jumlah']      ?? 0);
    $keterangan = sanitize($_POST['keterangan'] ?? '');

    if (!$tanggal || !$kategori || $jumlah <= 0 || !$keterangan) {
        flash('error', 'Semua field wajib diisi dan jumlah harus > 0.');
        redirect(BASE_URL.'/pages/karyawan/reimburse.php');
    }

    // Upload bukti foto
    $namaBukti = '';
    if (!empty($_FILES['bukti']['tmp_name']) && $_FILES['bukti']['size'] > 0) {
        $ext = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','pdf'])) {
            $namaBukti = 'reimb_'.$uid.'_'.date('YmdHis').'.'.$ext;
            move_uploaded_file($_FILES['bukti']['tmp_name'], $uploadDir.$namaBukti);
        }
    }

    $tanggal_e   = esc($tanggal);
    $kategori_e  = esc($kategori);
    $ket_e       = esc($keterangan);
    $bukti_e     = esc($namaBukti);
    db()->query("INSERT INTO reimburse (user_id,tanggal,kategori,jumlah,keterangan,bukti)
        VALUES ($uid,'$tanggal_e','$kategori_e',$jumlah,'$ket_e','$bukti_e')");

    if (db()->error) flash('error', 'Gagal submit: '.db()->error);
    else {
        // Notifikasi ke HR
        $hrList = db()->query("SELECT id FROM users WHERE role IN ('hrd','admin') AND status='aktif'");
        if ($hrList) while ($h = $hrList->fetch_assoc()) {
            $hid = (int)$h['id'];
            $n   = esc($user['nama']);
            db()->query("INSERT INTO notifikasi (user_id,judul,pesan,tipe,link)
                VALUES ($hid,'Reimburse Baru','$n mengajukan reimburse ".esc($kategori)." — ".esc('Rp '.number_format($jumlah,0,',','.'))."','info','".esc(BASE_URL.'/pages/admin/reimburse.php')."')");
        }
        flash('success', 'Pengajuan reimburse berhasil dikirim.');
    }
    redirect(BASE_URL.'/pages/karyawan/reimburse.php');
}

// ── Data ─────────────────────────────────────────────────────
$list = db()->query("SELECT * FROM reimburse WHERE user_id=$uid ORDER BY created_at DESC LIMIT 50");
$totDisetujui = (float)db()->query("SELECT COALESCE(SUM(jumlah),0) t FROM reimburse WHERE user_id=$uid AND status='disetujui'")->fetch_assoc()['t'];

$pageTitle  = 'Reimburse';
$activePage = 'reimburse';
$topbarActions = '<button class="btn btn-primary" onclick="openModal(\'mReim\')">+ Ajukan Reimburse</button>';
include __DIR__.'/../../includes/header.php';
?>

<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1rem">
    <?php
    $totP = (int)db()->query("SELECT COUNT(*) c FROM reimburse WHERE user_id=$uid AND status='pending'")->fetch_assoc()['c'];
    $totD = (int)db()->query("SELECT COUNT(*) c FROM reimburse WHERE user_id=$uid AND status='disetujui'")->fetch_assoc()['c'];
    $totT = (int)db()->query("SELECT COUNT(*) c FROM reimburse WHERE user_id=$uid AND status='ditolak'")->fetch_assoc()['c'];
    ?>
    <div class="stat-card amber"><div class="stat-label">Pending</div><div class="stat-value"><?= $totP ?></div></div>
    <div class="stat-card green"><div class="stat-label">Disetujui</div><div class="stat-value"><?= $totD ?></div><div class="stat-sub"><?= formatRp($totDisetujui) ?></div></div>
    <div class="stat-card red"><div class="stat-label">Ditolak</div><div class="stat-value"><?= $totT ?></div></div>
</div>

<div class="card">
<div class="tbl-wrap">
<table>
    <thead><tr><th>Tanggal</th><th>Kategori</th><th>Jumlah</th><th>Keterangan</th><th>Bukti</th><th>Status</th><th>Catatan HR</th></tr></thead>
    <tbody>
    <?php if (!$list || $list->num_rows===0): ?>
    <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--text-m)">Belum ada pengajuan reimburse.</td></tr>
    <?php else: while ($r = $list->fetch_assoc()):
        $bs = ['pending'=>'badge-amber','disetujui'=>'badge-green','ditolak'=>'badge-red'];
    ?>
    <tr>
        <td class="text-sm"><?= formatTgl($r['tanggal']) ?></td>
        <td class="text-sm"><?= htmlspecialchars($r['kategori']) ?></td>
        <td class="mono fw-700 text-green"><?= formatRp((float)$r['jumlah']) ?></td>
        <td class="text-sm" style="max-width:200px"><?= htmlspecialchars($r['keterangan']) ?></td>
        <td>
            <?php if ($r['bukti']): ?>
            <a href="<?= BASE_URL ?>/assets/uploads/reimburse/<?= htmlspecialchars($r['bukti']) ?>" target="_blank"
               class="btn btn-sm">📎 Lihat</a>
            <?php else: ?><span class="text-muted text-xs">—</span><?php endif; ?>
        </td>
        <td><span class="badge <?= $bs[$r['status']] ?? 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
        <td class="text-sm text-muted" style="max-width:160px"><?= htmlspecialchars($r['catatan_approver'] ?? '—') ?></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>
</div>
</div>

<!-- Modal Ajukan Reimburse -->
<div class="modal-overlay" id="mReim">
<div class="modal">
    <div class="modal-header"><span class="modal-title">Ajukan Reimburse</span><button class="modal-close" onclick="closeModal('mReim')">✕</button></div>
    <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="submit_reimb" value="1">
    <div class="modal-body">
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Tanggal *</label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Kategori *</label>
                <select name="kategori" class="form-control" required>
                    <option value="">— Pilih —</option>
                    <option value="Transportasi">Transportasi</option>
                    <option value="Makan & Minum">Makan & Minum</option>
                    <option value="Akomodasi">Akomodasi</option>
                    <option value="Kesehatan">Kesehatan</option>
                    <option value="Operasional">Operasional</option>
                    <option value="Peralatan">Peralatan</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>
            <div class="form-group form-full">
                <label class="form-label">Jumlah (Rp) *</label>
                <input type="number" name="jumlah" class="form-control" min="1" required placeholder="Contoh: 150000">
            </div>
            <div class="form-group form-full">
                <label class="form-label">Keterangan / Tujuan *</label>
                <textarea name="keterangan" class="form-control" rows="3" required placeholder="Jelaskan keperluan reimburse..."></textarea>
            </div>
            <div class="form-group form-full">
                <label class="form-label">Bukti (Foto / PDF) <span class="form-hint">Opsional, maks 5MB</span></label>
                <input type="file" name="bukti" class="form-control" accept="image/*,.pdf">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('mReim')">Batal</button>
        <button type="submit" class="btn btn-primary">Kirim Pengajuan</button>
    </div>
    </form>
</div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
