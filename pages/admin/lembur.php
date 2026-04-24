<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();
$me = currentUser();

// Approve / Tolak
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lid    = (int)($_POST['id']     ?? 0);
    $action = sanitize($_POST['action']  ?? '');
    $cat    = sanitize($_POST['catatan'] ?? '');

    if ($lid && in_array($action, ['disetujui','ditolak'], true)) {
        $upah = 0.0;
        if ($action === 'disetujui') {
            $row = db()->query("SELECT l.durasi_menit, j.gaji_pokok
                FROM lembur l
                JOIN users u ON l.user_id=u.id
                LEFT JOIN jabatan j ON u.jabatan_id=j.id
                WHERE l.id=$lid")->fetch_assoc();
            if ($row) {
                $tarifJam = ($row['gaji_pokok'] ?? 0) / 173;
                $jamLembur = ($row['durasi_menit'] ?? 0) / 60;
                $upah = $tarifJam * 1.5 * $jamLembur;
            }
        }
        // bind_param: s=action, i=disetujui_oleh, s=catatan, d=upah_lembur, i=id
        $stmt = dbPrepare("UPDATE lembur SET status=?,disetujui_oleh=?,catatan_approver=?,upah_lembur=?,updated_at=NOW() WHERE id=?");
        $meId = (int)$me['id'];
        $stmt->bind_param('sisdi', $action, $meId, $cat, $upah, $lid);
        $stmt->execute();
        $stmt->close();
        flash('success', "Lembur berhasil $action.");
    }
    redirect(BASE_URL.'/pages/admin/lembur.php');
}

$stF  = sanitize($_GET['status'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$per  = 15; $off = ($page-1)*$per;

$where = ['1=1'];
if ($stF) $where[] = "l.status='".esc($stF)."'";
$w = implode(' AND ', $where);

$total = (int)db()->query("SELECT COUNT(*) c FROM lembur l WHERE $w")->fetch_assoc()['c'];
$pages = max(1,(int)ceil($total/$per));
$rows  = db()->query("SELECT l.*,u.nama,u.nip,d.nama dept_nama
    FROM lembur l
    JOIN users u ON l.user_id=u.id
    LEFT JOIN departemen d ON u.departemen_id=d.id
    WHERE $w ORDER BY l.created_at DESC LIMIT $per OFFSET $off");

$pageTitle  = 'Manajemen Lembur';
$activePage = 'lembur';
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
        <th>Karyawan</th><th>Tanggal</th><th>Mulai</th><th>Selesai</th>
        <th>Durasi</th><th>Alasan</th><th>Upah</th><th>Status</th><th style="width:90px">Aksi</th>
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
        <td class="text-sm"><?= formatTgl($r['tanggal']) ?></td>
        <td class="mono text-sm"><?= substr($r['jam_mulai'],0,5) ?></td>
        <td class="mono text-sm"><?= substr($r['jam_selesai'],0,5) ?></td>
        <td><span class="badge badge-blue"><?= round(($r['durasi_menit']??0)/60,1) ?> jam</span></td>
        <td class="text-sm text-muted" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['alasan'] ?? '') ?></td>
        <td class="text-sm"><?= $r['upah_lembur'] > 0 ? formatRp($r['upah_lembur']) : '—' ?></td>
        <td><span class="badge <?= $bs[$r['status']] ?? 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
        <td>
            <?php if ($r['status'] === 'pending'): ?>
            <div class="flex gap-2">
                <button class="btn btn-sm btn-primary" onclick="bukaApproval(<?= $r['id'] ?>,'disetujui')">✓</button>
                <button class="btn btn-sm btn-danger"  onclick="bukaApproval(<?= $r['id'] ?>,'ditolak')">✗</button>
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

<!-- Modal Approval -->
<div class="modal-overlay" id="mApproval">
<div class="modal">
    <div class="modal-header">
        <span class="modal-title" id="mApprovalTitle">Konfirmasi</span>
        <button class="modal-close" onclick="closeModal('mApproval')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="id"     id="aId">
    <input type="hidden" name="action" id="aAction">
    <div class="modal-body">
        <div class="form-group">
            <label class="form-label">Catatan (opsional)</label>
            <textarea name="catatan" class="form-control" rows="3" placeholder="Tambahkan catatan..."></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('mApproval')">Batal</button>
        <button type="submit" class="btn btn-primary" id="aBtnSubmit">Konfirmasi</button>
    </div>
    </form>
</div>
</div>

<script>
function bukaApproval(id, action) {
    document.getElementById('aId').value = id;
    document.getElementById('aAction').value = action;
    document.getElementById('mApprovalTitle').textContent = action === 'disetujui' ? '✓ Setujui Lembur' : '✗ Tolak Lembur';
    document.getElementById('aBtnSubmit').className = 'btn ' + (action === 'disetujui' ? 'btn-primary' : 'btn-danger');
    openModal('mApproval');
}
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
