<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();
$me = currentUser();

// ── Approve / Reject ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $rid     = (int)$_POST['rid'];
    $aksi    = $_POST['aksi'] === 'setujui' ? 'disetujui' : 'ditolak';
    $catatan = sanitize($_POST['catatan'] ?? '');
    $meId    = (int)$me['id'];
    $cat_e   = esc($catatan);
    db()->query("UPDATE reimburse SET status='$aksi', disetujui_oleh=$meId,
        catatan_approver='$cat_e', tanggal_approve=CURDATE()
        WHERE id=$rid");

    // Notifikasi ke karyawan
    $r = db()->query("SELECT rb.*,u.nama FROM reimburse rb JOIN users u ON rb.user_id=u.id WHERE rb.id=$rid")->fetch_assoc();
    if ($r) {
        $uid2  = (int)$r['user_id'];
        $label = $aksi === 'disetujui' ? 'disetujui ✓' : 'ditolak ✗';
        $ket2  = esc($r['kategori']);
        $jml   = esc('Rp '.number_format((float)$r['jumlah'],0,',','.'));
        $link  = esc(BASE_URL.'/pages/karyawan/reimburse.php');
        db()->query("INSERT INTO notifikasi (user_id,judul,pesan,tipe,link)
            VALUES ($uid2,'Reimburse $label',
            'Pengajuan reimburse $ket2 ($jml) telah $aksi.',
            '".($aksi==='disetujui'?'sukses':'bahaya')."','$link')");
    }
    flash('success', 'Reimburse '.($aksi==='disetujui'?'disetujui':'ditolak').'.');
    redirect(BASE_URL.'/pages/admin/reimburse.php');
}

// ── Filter & List ─────────────────────────────────────────────
$stF  = sanitize($_GET['status'] ?? '');
$q    = sanitize($_GET['q']      ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$per  = 15; $off = ($page-1)*$per;

$where = ['1'];
if ($stF) $where[] = "rb.status='".esc($stF)."'";
if ($q)   $where[] = "(u.nama LIKE '%".esc($q)."%' OR rb.kategori LIKE '%".esc($q)."%')";
$w = implode(' AND ', $where);

$total  = (int)db()->query("SELECT COUNT(*) c FROM reimburse rb JOIN users u ON rb.user_id=u.id WHERE $w")->fetch_assoc()['c'];
$pages  = max(1,(int)ceil($total/$per));
$rows   = db()->query("SELECT rb.*,u.nama,u.nip,d.nama dept_nama
    FROM reimburse rb
    JOIN users u ON rb.user_id=u.id
    LEFT JOIN departemen d ON u.departemen_id=d.id
    WHERE $w ORDER BY rb.created_at DESC LIMIT $per OFFSET $off");

$totPend  = (int)db()->query("SELECT COUNT(*) c FROM reimburse WHERE status='pending'")->fetch_assoc()['c'];
$totDis   = (float)db()->query("SELECT COALESCE(SUM(jumlah),0) t FROM reimburse WHERE status='disetujui'")->fetch_assoc()['t'];

$pageTitle  = 'Reimburse';
$activePage = 'reimburse';
include __DIR__.'/../../includes/header.php';
?>

<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1rem">
    <div class="stat-card amber"><div class="stat-label">Menunggu Persetujuan</div><div class="stat-value"><?= $totPend ?></div></div>
    <div class="stat-card green"><div class="stat-label">Total Disetujui</div><div class="stat-value" style="font-size:15px"><?= formatRp($totDis) ?></div></div>
    <div class="stat-card blue"><div class="stat-label">Total Pengajuan</div><div class="stat-value"><?= $total ?></div></div>
</div>

<form method="GET">
<div class="toolbar">
    <div class="toolbar-left">
        <input type="text" name="q" class="search-box" placeholder="Cari nama / kategori..." value="<?= htmlspecialchars($q) ?>">
        <select name="status" class="sel-filter auto-submit">
            <option value="">Semua Status</option>
            <option value="pending"   <?= $stF==='pending'  ?'selected':'' ?>>Pending</option>
            <option value="disetujui" <?= $stF==='disetujui'?'selected':'' ?>>Disetujui</option>
            <option value="ditolak"   <?= $stF==='ditolak'  ?'selected':'' ?>>Ditolak</option>
        </select>
        <?php if ($stF||$q): ?><a href="?" class="btn btn-sm">Reset</a><?php endif; ?>
    </div>
    <div class="toolbar-right">
        <span class="text-muted text-sm"><?= $total ?> data</span>
        <button type="submit" class="btn btn-sm">Cari</button>
    </div>
</div>
</form>

<div class="card">
<div class="tbl-wrap">
<table>
    <thead><tr>
        <th>Karyawan</th><th>Tanggal</th><th>Kategori</th><th>Jumlah</th>
        <th>Keterangan</th><th>Bukti</th><th>Status</th><th>Aksi</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows || $rows->num_rows===0): ?>
    <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--text-m)">Tidak ada data reimburse</td></tr>
    <?php else: while ($r = $rows->fetch_assoc()):
        $bs = ['pending'=>'badge-amber','disetujui'=>'badge-green','ditolak'=>'badge-red'];
    ?>
    <tr>
        <td><div class="name-cell">
            <div class="avatar av-sm" style="background:<?= avatarBg((int)$r['user_id']) ?>"><?= initials($r['nama']) ?></div>
            <div><div class="nc-name"><?= htmlspecialchars($r['nama']) ?></div>
            <div class="nc-sub"><?= htmlspecialchars($r['dept_nama'] ?? '—') ?></div></div>
        </div></td>
        <td class="text-sm"><?= formatTgl($r['tanggal']) ?></td>
        <td class="text-sm"><?= htmlspecialchars($r['kategori']) ?></td>
        <td class="mono fw-700 text-green"><?= formatRp((float)$r['jumlah']) ?></td>
        <td class="text-sm" style="max-width:180px"><?= htmlspecialchars($r['keterangan']) ?></td>
        <td>
            <?php if ($r['bukti']): ?>
            <a href="<?= BASE_URL ?>/assets/uploads/reimburse/<?= htmlspecialchars($r['bukti']) ?>" target="_blank" class="btn btn-sm">📎</a>
            <?php else: ?><span class="text-muted text-xs">—</span><?php endif; ?>
        </td>
        <td><span class="badge <?= $bs[$r['status']] ?? 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
        <td>
            <?php if ($r['status'] === 'pending'): ?>
            <div class="flex gap-2">
                <button class="btn btn-sm btn-primary"
                    onclick="aksiReimb(<?= $r['id'] ?>,'setujui')">✓</button>
                <button class="btn btn-sm btn-danger"
                    onclick="aksiReimb(<?= $r['id'] ?>,'tolak')">✕</button>
            </div>
            <?php else: ?>
            <span class="text-muted text-xs"><?= htmlspecialchars($r['catatan_approver'] ?? '') ?></span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>
</div>
<div class="pagination">
    <span>Hal <?= $page ?>/<?= $pages ?> · <?= $total ?> total</span>
    <div class="page-btns">
        <?php if($page>1):?><a href="?q=<?=urlencode($q)?>&status=<?=$stF?>&page=<?=$page-1?>" class="page-btn">‹</a><?php endif;?>
        <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++):?><a href="?q=<?=urlencode($q)?>&status=<?=$stF?>&page=<?=$i?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a><?php endfor;?>
        <?php if($page<$pages):?><a href="?q=<?=urlencode($q)?>&status=<?=$stF?>&page=<?=$page+1?>" class="page-btn">›</a><?php endif;?>
    </div>
</div>
</div>

<!-- Modal Aksi Reimburse -->
<div class="modal-overlay" id="mAksi">
<div class="modal">
    <div class="modal-header"><span class="modal-title" id="mAksi_title">Konfirmasi</span><button class="modal-close" onclick="closeModal('mAksi')">✕</button></div>
    <form method="POST">
    <input type="hidden" name="rid"  id="mAksi_rid">
    <input type="hidden" name="aksi" id="mAksi_aksi">
    <div class="modal-body">
        <div class="form-group">
            <label class="form-label">Catatan (opsional)</label>
            <textarea name="catatan" class="form-control" rows="3" placeholder="Catatan untuk karyawan..."></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('mAksi')">Batal</button>
        <button type="submit" id="mAksi_btn" class="btn btn-primary">Konfirmasi</button>
    </div>
    </form>
</div>
</div>

<script>
function aksiReimb(rid, aksi) {
    document.getElementById('mAksi_rid').value  = rid;
    document.getElementById('mAksi_aksi').value = aksi;
    var isSetuju = aksi === 'setujui';
    document.getElementById('mAksi_title').textContent = isSetuju ? '✓ Setujui Reimburse' : '✕ Tolak Reimburse';
    document.getElementById('mAksi_btn').className = 'btn ' + (isSetuju ? 'btn-primary' : 'btn-danger');
    document.getElementById('mAksi_btn').textContent = isSetuju ? 'Setujui' : 'Tolak';
    openModal('mAksi');
}
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
