<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$deptF = (int)($_GET['dept']  ?? 0);
$stF   = sanitize($_GET['status'] ?? '');
$page  = max(1,(int)($_GET['page'] ?? 1));
$per   = 15; $off = ($page-1)*$per;

$where = ["MONTH(a.tanggal)=$bulan","YEAR(a.tanggal)=$tahun"];
if ($deptF) $where[] = "u.departemen_id=$deptF";
if ($stF)   $where[] = "a.status_kehadiran='".esc($stF)."'";
$w = implode(' AND ', $where);

$total = (int)db()->query("SELECT COUNT(*) c FROM absensi a JOIN users u ON a.user_id=u.id WHERE $w")->fetch_assoc()['c'];
$pages = max(1,(int)ceil($total/$per));
$rows  = db()->query("SELECT a.*,u.nama,u.nip,d.nama dept_nama,s.nama shift_nama
    FROM absensi a JOIN users u ON a.user_id=u.id
    LEFT JOIN departemen d ON u.departemen_id=d.id
    LEFT JOIN shift s ON a.shift_id=s.id
    WHERE $w ORDER BY a.tanggal DESC,a.jam_masuk DESC LIMIT $per OFFSET $off");

$sum = db()->query("SELECT
    COUNT(*) total, SUM(a.status_kehadiran='hadir') hadir,
    SUM(a.status_kehadiran='alpha') alpha, SUM(a.status_masuk='terlambat') terlambat
    FROM absensi a JOIN users u ON a.user_id=u.id WHERE $w")->fetch_assoc();

$deptList   = getDepartemen();
$pageTitle  = 'Rekap Absensi';
$activePage = 'absensi';
include __DIR__.'/../../includes/header.php';
?>

<form method="GET">
<div class="toolbar mb-2">
    <div class="toolbar-left">
        <select name="bulan" class="sel-filter auto-submit">
            <?php for ($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m===$bulan?'selected':''?>><?=bulanNama($m)?></option><?php endfor;?>
        </select>
        <select name="tahun" class="sel-filter auto-submit">
            <?php for ($y=(int)date('Y');$y>=(int)date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$y===$tahun?'selected':''?>><?=$y?></option><?php endfor;?>
        </select>
        <select name="dept" class="sel-filter auto-submit">
            <option value="">Semua Dept.</option>
            <?php foreach ($deptList as $d): ?><option value="<?=$d['id']?>" <?=$deptF===$d['id']?'selected':''?>><?=htmlspecialchars($d['nama'])?></option><?php endforeach;?>
        </select>
        <select name="status" class="sel-filter auto-submit">
            <option value="">Semua Status</option>
            <option value="hadir" <?=$stF==='hadir'?'selected':''?>>Hadir</option>
            <option value="alpha" <?=$stF==='alpha'?'selected':''?>>Alpha</option>
            <option value="cuti"  <?=$stF==='cuti' ?'selected':''?>>Cuti</option>
        </select>
    </div>
    <button type="submit" class="btn btn-sm">Filter</button>
</div>
</form>

<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1rem">
    <div class="stat-card green"><div class="stat-label">Total</div><div class="stat-value"><?=(int)$sum['total']?></div></div>
    <div class="stat-card blue"><div class="stat-label">Hadir</div><div class="stat-value"><?=(int)$sum['hadir']?></div></div>
    <div class="stat-card amber"><div class="stat-label">Terlambat</div><div class="stat-value"><?=(int)$sum['terlambat']?></div></div>
    <div class="stat-card red"><div class="stat-label">Alpha</div><div class="stat-value"><?=(int)$sum['alpha']?></div></div>
</div>

<div class="card">
<div class="tbl-wrap">
<table>
    <thead><tr><th>Karyawan</th><th>Tanggal</th><th>Shift</th><th>Masuk</th><th>Keluar</th><th>GPS</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (!$rows || $rows->num_rows===0): ?>
    <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--text-m)">Tidak ada data</td></tr>
    <?php else: while ($r = $rows->fetch_assoc()):
        $bs = ['tepat'=>'badge-green','terlambat'=>'badge-amber','alpha'=>'badge-red','izin'=>'badge-blue'];
        $bc = $bs[$r['status_masuk']] ?? 'badge-gray';
        $lbl = $r['status_masuk'] === 'terlambat' ? 'Terlambat' : ucfirst($r['status_kehadiran']);
    ?>
    <tr>
        <td><div class="name-cell">
            <div class="avatar av-sm" style="background:<?=avatarBg((int)$r['user_id'])?>"><?=initials($r['nama'])?></div>
            <div><div class="nc-name"><?=htmlspecialchars($r['nama'])?></div>
            <div class="nc-sub"><?=htmlspecialchars($r['nip'])?></div></div>
        </div></td>
        <td class="text-sm"><?=formatTgl($r['tanggal'])?></td>
        <td><span class="badge badge-purple text-xs"><?=htmlspecialchars($r['shift_nama']??'—')?></span></td>
        <td class="mono text-sm"><?=$r['jam_masuk'] ? date('H:i',strtotime($r['jam_masuk'])) : '—'?></td>
        <td class="mono text-sm"><?=$r['jam_keluar']? date('H:i',strtotime($r['jam_keluar'])): '—'?></td>
        <td class="text-sm text-muted"><?=$r['jarak_masuk'] ? $r['jarak_masuk'].'m' : '—'?></td>
        <td><span class="badge <?=$bc?>"><?=$lbl?></span></td>
    </tr>
    <?php endwhile; endif;?>
    </tbody>
</table>
</div>
<div class="pagination">
    <span><?=$total?> record</span>
    <div class="page-btns">
        <?php if($page>1):?><a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&dept=<?=$deptF?>&status=<?=$stF?>&page=<?=$page-1?>" class="page-btn">‹</a><?php endif;?>
        <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++):?><a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&dept=<?=$deptF?>&status=<?=$stF?>&page=<?=$i?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a><?php endfor;?>
        <?php if($page<$pages):?><a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&dept=<?=$deptF?>&status=<?=$stF?>&page=<?=$page+1?>" class="page-btn">›</a><?php endif;?>
    </div>
</div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
