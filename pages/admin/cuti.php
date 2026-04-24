<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();
$me = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid    = (int)($_POST['id']     ?? 0);
    $action = sanitize($_POST['action']  ?? '');
    $cat    = sanitize($_POST['catatan'] ?? '');

    if ($cid && in_array($action, ['disetujui','ditolak'], true)) {
        $cuti = db()->query("SELECT * FROM pengajuan_cuti WHERE id=$cid")->fetch_assoc();
        $stmt = dbPrepare("UPDATE pengajuan_cuti SET status=?,disetujui_oleh=?,catatan_approver=?,updated_at=NOW() WHERE id=?");
        $meId = (int)$me['id'];
        $stmt->bind_param('sisi', $action, $meId, $cat, $cid);
        $stmt->execute();
        $stmt->close();
        if ($action === 'disetujui' && $cuti) {
            $hari = (int)($cuti['jumlah_hari'] ?? 0);
            $uid  = (int)$cuti['user_id'];
            db()->query("UPDATE users SET sisa_cuti=GREATEST(0,sisa_cuti-$hari) WHERE id=$uid");
        }
        flash('success', "Pengajuan cuti $action.");
    }
    redirect(BASE_URL.'/pages/admin/cuti.php');
}

$stF  = sanitize($_GET['status'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$per  = 15; $off = ($page-1)*$per;
$where = ['1=1'];
if ($stF) $where[] = "c.status='".esc($stF)."'";
$w = implode(' AND ', $where);

$total = (int)db()->query("SELECT COUNT(*) cnt FROM pengajuan_cuti c WHERE $w")->fetch_assoc()['cnt'];
$pages = max(1,(int)ceil($total/$per));
$rows  = db()->query("SELECT c.*,u.nama,u.nip,u.sisa_cuti,jc.nama jenis_nama,d.nama dept_nama
    FROM pengajuan_cuti c
    JOIN users u ON c.user_id=u.id
    JOIN jenis_cuti jc ON c.jenis_cuti_id=jc.id
    LEFT JOIN departemen d ON u.departemen_id=d.id
    WHERE $w ORDER BY c.created_at DESC LIMIT $per OFFSET $off");

$pageTitle  = 'Pengajuan Cuti';
$activePage = 'cuti';
include __DIR__.'/../../includes/header.php';
?>

<div class="toolbar mb-2">
    <div class="toolbar-left">
        <?php foreach ([''=>'Semua','pending'=>'Pending','disetujui'=>'Disetujui','ditolak'=>'Ditolak'] as $v=>$lbl): ?>
        <a href="?status=<?= $v ?>" class="btn btn-sm <?= $stF===$v?'btn-primary':'' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
    </div>
    <span class="text-muted text-sm"><?= $total ?> pengajuan</span>
</div>

<div class="card">
<div class="tbl-wrap">
<table>
    <thead><tr>
        <th>Karyawan</th><th>Jenis</th><th>Mulai</th><th>Selesai</th>
        <th>Lama</th><th>Sisa</th><th>Alasan</th><th>Status</th><th style="width:90px">Aksi</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows || $rows->num_rows===0): ?>
    <tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--text-m)">Tidak ada data</td></tr>
    <?php else: while ($r = $rows->fetch_assoc()):
        $bs = ['pending'=>'badge-amber','disetujui'=>'badge-green','ditolak'=>'badge-red'];
    ?>
    <tr>
        <td><div class="name-cell">
            <div class="avatar av-sm" style="background:<?= avatarBg((int)$r['user_id']) ?>"><?= initials($r['nama']) ?></div>
            <div><div class="nc-name"><?= htmlspecialchars($r['nama']) ?></div>
            <div class="nc-sub"><?= htmlspecialchars($r['dept_nama'] ?? '—') ?></div></div>
        </div></td>
        <td><span class="badge badge-blue"><?= htmlspecialchars($r['jenis_nama']) ?></span></td>
        <td class="text-sm"><?= formatTgl($r['tanggal_mulai']) ?></td>
        <td class="text-sm"><?= formatTgl($r['tanggal_selesai']) ?></td>
        <td><span class="badge badge-purple"><?= (int)$r['jumlah_hari'] ?> hari</span></td>
        <td class="mono text-sm"><?= (int)$r['sisa_cuti'] ?> hr</td>
        <td class="text-sm text-muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['alasan'] ?? '') ?></td>
        <td><span class="badge <?= $bs[$r['status']] ?? 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
        <td>
            <?php if ($r['status'] === 'pending'): ?>
            <div class="flex gap-2">
                <button class="btn btn-sm btn-primary" onclick="bukaCuti(<?= $r['id'] ?>,<?= (int)$r['jumlah_hari'] ?>,'disetujui')">✓</button>
                <button class="btn btn-sm btn-danger"  onclick="bukaCuti(<?= $r['id'] ?>,0,'ditolak')">✗</button>
            </div>
            <?php else: echo '—'; endif; ?>
        </td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>
</div>
<div class="pagination">
    <span><?= $total ?> total</span>
    <div class="page-btns">
        <?php if($page>1):?><a href="?status=<?=$stF?>&page=<?=$page-1?>" class="page-btn">‹</a><?php endif;?>
        <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++):?><a href="?status=<?=$stF?>&page=<?=$i?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a><?php endfor;?>
        <?php if($page<$pages):?><a href="?status=<?=$stF?>&page=<?=$page+1?>" class="page-btn">›</a><?php endif;?>
    </div>
</div>
</div>

<div class="modal-overlay" id="mCuti">
<div class="modal">
    <div class="modal-header">
        <span class="modal-title" id="mCutiTitle">Konfirmasi</span>
        <button class="modal-close" onclick="closeModal('mCuti')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="id"     id="cId">
    <input type="hidden" name="action" id="cAction">
    <div class="modal-body">
        <div id="cInfo" class="alert alert-info mb-2" style="display:none"></div>
        <div class="form-group">
            <label class="form-label">Catatan Approver</label>
            <textarea name="catatan" class="form-control" rows="3" placeholder="Opsional..."></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('mCuti')">Batal</button>
        <button type="submit" class="btn btn-primary" id="cSubmitBtn">Konfirmasi</button>
    </div>
    </form>
</div>
</div>

<script>
function bukaCuti(id, hari, action) {
    document.getElementById('cId').value = id;
    document.getElementById('cAction').value = action;
    document.getElementById('mCutiTitle').textContent = action === 'disetujui' ? '✓ Setujui Cuti' : '✗ Tolak Cuti';
    document.getElementById('cSubmitBtn').className = 'btn ' + (action === 'disetujui' ? 'btn-primary' : 'btn-danger');
    var info = document.getElementById('cInfo');
    if (action === 'disetujui' && hari > 0) {
        info.style.display = 'block';
        info.textContent = 'Saldo cuti karyawan akan berkurang ' + hari + ' hari.';
    } else { info.style.display = 'none'; }
    openModal('mCuti');
}
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
