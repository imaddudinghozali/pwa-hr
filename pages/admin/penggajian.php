<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();
$me = currentUser();

// ── Generate slip ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $uid   = (int)$_POST['user_id'];
    $bulan = (int)$_POST['bulan'];
    $tahun = (int)$_POST['tahun'];

    if (!$uid || !$bulan || !$tahun) { flash('error','Data tidak lengkap.'); redirect(BASE_URL.'/pages/admin/penggajian.php'); }

    $u = db()->query("SELECT u.*,j.gaji_pokok,j.tunjangan_jabatan
        FROM users u LEFT JOIN jabatan j ON u.jabatan_id=j.id
        WHERE u.id=$uid LIMIT 1")->fetch_assoc();
    if (!$u) { flash('error','Karyawan tidak ditemukan.'); redirect(BASE_URL.'/pages/admin/penggajian.php'); }

    // Kehadiran
    $hKerja = $hHadir = $hAlpha = 0;
    $absen  = db()->query("SELECT status_kehadiran FROM absensi
        WHERE user_id=$uid AND MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun");
    while ($a = $absen->fetch_assoc()) {
        $hKerja++;
        if ($a['status_kehadiran'] === 'hadir') $hHadir++;
        if ($a['status_kehadiran'] === 'alpha') $hAlpha++;
    }

    // Lembur
    $lemRow   = db()->query("SELECT COALESCE(SUM(durasi_menit),0) dm, COALESCE(SUM(upah_lembur),0) ul
        FROM lembur WHERE user_id=$uid AND MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun AND status='disetujui'")->fetch_assoc();

    // Hitung
    $gPokok      = (float)($u['gaji_pokok']        ?? 0);
    $tJabatan    = (float)($u['tunjangan_jabatan']  ?? 0);
    $tMakan      = 750000.0;
    $tTransp     = 500000.0;
    $uLembur     = (float)($lemRow['ul']            ?? 0);
    $jamLembur   = round((float)($lemRow['dm'] ?? 0) / 60, 2);
    $potAlpha    = $hAlpha > 0 && $hKerja > 0 ? ($gPokok / 22) * $hAlpha : 0.0;
    $potBpjsTK   = $gPokok * 0.02;
    $potBpjsKes  = $gPokok * 0.01;
    $potPph      = ($gPokok + $tJabatan) * 0.05;
    $gBersih     = ($gPokok + $tJabatan + $tMakan + $tTransp + $uLembur)
                 - ($potAlpha + $potBpjsTK + $potBpjsKes + $potPph);
    $meId = (int)$me['id'];

    // Upsert via direct query (avoids bind_param count issues)
    $check = db()->query("SELECT id FROM slip_gaji WHERE user_id=$uid AND bulan=$bulan AND tahun=$tahun");
    if ($check && $check->num_rows > 0) {
        $sid = (int)$check->fetch_assoc()['id'];
        db()->query("UPDATE slip_gaji SET
            gaji_pokok=$gPokok, tunjangan_jabatan=$tJabatan,
            tunjangan_makan=$tMakan, tunjangan_transport=$tTransp,
            upah_lembur=$uLembur, potongan_absen=$potAlpha,
            potongan_bpjs_tk=$potBpjsTK, potongan_bpjs_kes=$potBpjsKes,
            potongan_pph21=$potPph, gaji_bersih=$gBersih,
            hari_kerja=$hKerja, hari_hadir=$hHadir, hari_alpha=$hAlpha,
            total_lembur_jam=$jamLembur, dibuat_oleh=$meId, status='draft'
            WHERE id=$sid");
    } else {
        db()->query("INSERT INTO slip_gaji
            (user_id,bulan,tahun,gaji_pokok,tunjangan_jabatan,tunjangan_makan,
             tunjangan_transport,upah_lembur,potongan_absen,potongan_bpjs_tk,
             potongan_bpjs_kes,potongan_pph21,gaji_bersih,
             hari_kerja,hari_hadir,hari_alpha,total_lembur_jam,dibuat_oleh)
            VALUES
            ($uid,$bulan,$tahun,$gPokok,$tJabatan,$tMakan,
             $tTransp,$uLembur,$potAlpha,$potBpjsTK,
             $potBpjsKes,$potPph,$gBersih,
             $hKerja,$hHadir,$hAlpha,$jamLembur,$meId)");
    }
    if (db()->error) flash('error', 'DB Error: '.db()->error);
    else flash('success', 'Slip gaji berhasil di-generate.');
    redirect(BASE_URL.'/pages/admin/penggajian.php?bulan='.$bulan.'&tahun='.$tahun);
}

// ── Bayar ──────────────────────────────────────────────────────
if (isset($_GET['bayar'])) {
    $sid = (int)$_GET['bayar'];
    db()->query("UPDATE slip_gaji SET status='dibayar',tanggal_bayar=CURDATE() WHERE id=$sid");
    flash('success','Gaji ditandai sudah dibayar.');
    redirect(BASE_URL.'/pages/admin/penggajian.php');
}

// ── List ───────────────────────────────────────────────────────
$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$page  = max(1,(int)($_GET['page'] ?? 1));
$per   = 15; $off = ($page-1)*$per;

$total     = (int)db()->query("SELECT COUNT(*) c FROM slip_gaji WHERE bulan=$bulan AND tahun=$tahun")->fetch_assoc()['c'];
$pages     = max(1,(int)ceil($total/$per));
$rows      = db()->query("SELECT sg.*,u.nama,u.nip,d.nama dept_nama
    FROM slip_gaji sg JOIN users u ON sg.user_id=u.id
    LEFT JOIN departemen d ON u.departemen_id=d.id
    WHERE sg.bulan=$bulan AND sg.tahun=$tahun ORDER BY u.nama LIMIT $per OFFSET $off");
$totGaji   = (float)db()->query("SELECT COALESCE(SUM(gaji_bersih),0) t FROM slip_gaji WHERE bulan=$bulan AND tahun=$tahun")->fetch_assoc()['t'];
$totBayar  = (int)db()->query("SELECT COUNT(*) c FROM slip_gaji WHERE bulan=$bulan AND tahun=$tahun AND status='dibayar'")->fetch_assoc()['c'];
$karList   = db()->query("SELECT id,nip,nama FROM users WHERE role='karyawan' AND status='aktif' ORDER BY nama")->fetch_all(MYSQLI_ASSOC);

$pageTitle     = 'Penggajian';
$activePage    = 'penggajian';
$topbarActions = '<button class="btn btn-primary" onclick="openModal(\'mGen\')">+ Generate Slip</button>';
include __DIR__.'/../../includes/header.php';
?>

<form method="GET" style="margin-bottom:1rem">
<div class="toolbar">
    <div class="toolbar-left">
        <select name="bulan" class="sel-filter auto-submit">
            <?php for ($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m===$bulan?'selected':''?>><?=bulanNama($m)?></option><?php endfor;?>
        </select>
        <select name="tahun" class="sel-filter auto-submit">
            <?php for ($y=(int)date('Y');$y>=(int)date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$y===$tahun?'selected':''?>><?=$y?></option><?php endfor;?>
        </select>
    </div>
    <button type="submit" class="btn btn-sm">Filter</button>
</div>
</form>

<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1rem">
    <div class="stat-card green">
        <div class="stat-label">Total Gaji Bersih</div>
        <div class="stat-value" style="font-size:16px"><?= formatRp($totGaji) ?></div>
        <div class="stat-sub"><?= bulanNama($bulan).' '.$tahun ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Slip Dibuat</div>
        <div class="stat-value"><?= $total ?></div>
    </div>
    <div class="stat-card amber">
        <div class="stat-label">Sudah Dibayar</div>
        <div class="stat-value"><?= $totBayar ?></div>
        <div class="stat-sub">dari <?= $total ?> slip</div>
    </div>
</div>

<div class="card">
<div class="tbl-wrap">
<table>
    <thead><tr>
        <th>Karyawan</th><th>Gaji Pokok</th><th>Tunjangan</th><th>Lembur</th>
        <th>Potongan</th><th>Gaji Bersih</th><th>Hadir</th><th>Status</th><th>Aksi</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows || $rows->num_rows===0): ?>
    <tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--text-m)">
        Belum ada slip gaji. Klik "Generate Slip" untuk membuat.
    </td></tr>
    <?php else: while ($r = $rows->fetch_assoc()):
        $tunj = (float)$r['tunjangan_jabatan']+(float)$r['tunjangan_makan']+(float)$r['tunjangan_transport'];
        $pot  = (float)$r['potongan_absen']+(float)$r['potongan_bpjs_tk']+(float)$r['potongan_bpjs_kes']+(float)$r['potongan_pph21'];
        $bsSt = ['draft'=>'badge-amber','final'=>'badge-blue','dibayar'=>'badge-green'];
    ?>
    <tr>
        <td><div class="name-cell">
            <div class="avatar av-sm" style="background:<?= avatarBg((int)$r['user_id']) ?>"><?= initials($r['nama']) ?></div>
            <div><div class="nc-name"><?= htmlspecialchars($r['nama']) ?></div>
            <div class="nc-sub"><?= htmlspecialchars($r['dept_nama'] ?? '—') ?></div></div>
        </div></td>
        <td class="mono text-sm"><?= formatRp($r['gaji_pokok']) ?></td>
        <td class="mono text-sm text-green"><?= formatRp($tunj) ?></td>
        <td class="mono text-sm text-blue"><?= formatRp($r['upah_lembur']) ?></td>
        <td class="mono text-sm text-red"><?= formatRp($pot) ?></td>
        <td class="mono fw-700"><?= formatRp($r['gaji_bersih']) ?></td>
        <td class="text-sm"><?= (int)$r['hari_hadir'] ?>/<?= (int)$r['hari_kerja'] ?></td>
        <td><span class="badge <?= $bsSt[$r['status']] ?? 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
        <td><div class="flex gap-2">
            <a href="<?= BASE_URL ?>/pages/admin/slip_detail.php?id=<?= $r['id'] ?>" class="btn btn-sm">Lihat</a>
            <?php if ($r['status'] !== 'dibayar'): ?>
            <a href="?bayar=<?= $r['id'] ?>&bulan=<?=$bulan?>&tahun=<?=$tahun?>" class="btn btn-sm btn-primary"
               onclick="return confirm('Tandai sudah dibayar?')">Bayar</a>
            <?php endif; ?>
        </div></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>
</div>
<div class="pagination">
    <span><?= $total ?> slip</span>
    <div class="page-btns">
        <?php if($page>1):?><a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&page=<?=$page-1?>" class="page-btn">‹</a><?php endif;?>
        <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++):?><a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&page=<?=$i?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a><?php endfor;?>
        <?php if($page<$pages):?><a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&page=<?=$page+1?>" class="page-btn">›</a><?php endif;?>
    </div>
</div>
</div>

<!-- Modal Generate -->
<div class="modal-overlay" id="mGen">
<div class="modal">
    <div class="modal-header"><span class="modal-title">Generate Slip Gaji</span><button class="modal-close" onclick="closeModal('mGen')">✕</button></div>
    <form method="POST">
    <input type="hidden" name="generate" value="1">
    <div class="modal-body">
        <div class="form-grid">
            <div class="form-group form-full">
                <label class="form-label">Karyawan *</label>
                <select name="user_id" class="form-control" required>
                    <option value="">— Pilih karyawan —</option>
                    <?php foreach ($karList as $k): ?>
                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama']) ?> (<?= $k['nip'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Bulan</label>
                <select name="bulan" class="form-control">
                    <?php for ($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m===$bulan?'selected':''?>><?=bulanNama($m)?></option><?php endfor;?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tahun</label>
                <select name="tahun" class="form-control">
                    <?php for ($y=(int)date('Y');$y>=(int)date('Y')-3;$y--): ?><option value="<?=$y?>"><?=$y?></option><?php endfor;?>
                </select>
            </div>
        </div>
        <div class="alert alert-info mt-1">Sistem otomatis menghitung dari data absensi + lembur yang sudah disetujui.</div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('mGen')">Batal</button>
        <button type="submit" class="btn btn-primary">Generate</button>
    </div>
    </form>
</div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
