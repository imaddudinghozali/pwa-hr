<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireLogin();
$uid = (int)currentUser()['id'];

if (isset($_GET['baca_semua'])) {
    db()->query("UPDATE notifikasi SET sudah_dibaca=1 WHERE user_id=$uid");
    redirect(BASE_URL.'/pages/karyawan/notifikasi.php');
}
if (isset($_GET['baca'])) {
    $id = (int)$_GET['baca'];
    db()->query("UPDATE notifikasi SET sudah_dibaca=1 WHERE id=$id AND user_id=$uid");
    redirect(BASE_URL.'/pages/karyawan/notifikasi.php');
}

$rows  = db()->query("SELECT * FROM notifikasi WHERE user_id=$uid ORDER BY created_at DESC LIMIT 60");
$belum = (int)db()->query("SELECT COUNT(*) c FROM notifikasi WHERE user_id=$uid AND sudah_dibaca=0")->fetch_assoc()['c'];

$pageTitle     = 'Notifikasi';
$activePage    = '';
$topbarActions = $belum > 0 ? '<a href="?baca_semua=1" class="btn btn-sm">Tandai semua dibaca</a>' : '';
include __DIR__.'/../../includes/header.php';
?>

<?php if ($belum > 0): ?>
<div class="alert alert-info mb-2"><?=$belum?> notifikasi belum dibaca</div>
<?php endif; ?>

<div class="card">
<?php if (!$rows || $rows->num_rows===0): ?>
<div style="text-align:center;padding:3rem;color:var(--text-m)">
    <div style="font-size:48px;margin-bottom:12px">🔔</div>
    <div>Tidak ada notifikasi</div>
</div>
<?php else: while ($r = $rows->fetch_assoc()):
    $icons = ['info'=>'ℹ️','sukses'=>'✅','peringatan'=>'⚠️','bahaya'=>'🚨'];
    $icon  = $icons[$r['tipe']] ?? 'ℹ️';
    $bg    = !$r['sudah_dibaca'] ? 'rgba(34,197,94,.05)' : 'transparent';
?>
<div style="display:flex;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border);background:<?=$bg?>;cursor:pointer"
    onclick="location.href='?baca=<?=$r['id']?>'">
    <div style="font-size:20px;flex-shrink:0;margin-top:2px"><?=$icon?></div>
    <div style="flex:1">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
            <strong style="font-size:13.5px"><?=htmlspecialchars($r['judul'])?></strong>
            <?php if (!$r['sudah_dibaca']): ?><span style="width:7px;height:7px;background:var(--green-500);border-radius:50%;flex-shrink:0"></span><?php endif;?>
        </div>
        <div style="font-size:13px;color:var(--text-2)"><?=htmlspecialchars($r['pesan'])?></div>
        <div style="font-size:11px;color:var(--text-m);margin-top:4px"><?=date('d M Y H:i',strtotime($r['created_at']))?></div>
    </div>
</div>
<?php endwhile; endif; ?>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
