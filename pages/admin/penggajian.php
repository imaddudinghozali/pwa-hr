<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();
$me = currentUser();

// ── Generate / Update slip ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $uid   = (int)$_POST['user_id'];
    $bulan = (int)$_POST['bulan'];
    $tahun = (int)$_POST['tahun'];

    if (!$uid || !$bulan || !$tahun) { flash('error','Data tidak lengkap.'); redirect(BASE_URL.'/pages/admin/penggajian.php'); }

    $u = db()->query("SELECT u.*,j.gaji_pokok gj_pokok,j.tunjangan_jabatan gj_tunj
        FROM users u LEFT JOIN jabatan j ON u.jabatan_id=j.id
        WHERE u.id=$uid LIMIT 1")->fetch_assoc();
    if (!$u) { flash('error','Karyawan tidak ditemukan.'); redirect(BASE_URL.'/pages/admin/penggajian.php'); }

    // Ambil override atau dari form (HR bisa edit sebelum generate)
    $gPokok   = isset($_POST['gaji_pokok'])        ? (float)$_POST['gaji_pokok']        : 0;
    $tJabatan = isset($_POST['tunjangan_jabatan'])  ? (float)$_POST['tunjangan_jabatan'] : 0;
    $tMakan   = isset($_POST['tunjangan_makan'])    ? (float)$_POST['tunjangan_makan']   : 750000.0;
    $tTransp  = isset($_POST['tunjangan_transport'])? (float)$_POST['tunjangan_transport']:500000.0;

    // Fallback ke override user atau jabatan jika field kosong
    if ($gPokok === 0.0) {
        $gPokok = $u['gaji_pokok_override'] !== null
            ? (float)$u['gaji_pokok_override']
            : (float)($u['gj_pokok'] ?? 0);
    }
    if ($tJabatan === 0.0) {
        $tJabatan = $u['tunjangan_jabatan_override'] !== null
            ? (float)$u['tunjangan_jabatan_override']
            : (float)($u['gj_tunj'] ?? 0);
    }

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

    // Reimburse yang disetujui bulan ini
    $reimbRow = db()->query("SELECT COALESCE(SUM(jumlah),0) rj
        FROM reimburse WHERE user_id=$uid
        AND MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun AND status='disetujui'")->fetch_assoc();

    $uLembur   = (float)($lemRow['ul']  ?? 0);
    $uReimb    = (float)($reimbRow['rj'] ?? 0);
    $jamLembur = round((float)($lemRow['dm'] ?? 0) / 60, 2);
    $potAlpha  = $hAlpha > 0 && $hKerja > 0 ? ($gPokok / 22) * $hAlpha : 0.0;
    $potBpjsTK = $gPokok * 0.02;
    $potBpjsKes= $gPokok * 0.01;
    $potPph    = ($gPokok + $tJabatan) * 0.05;
    $potLain   = isset($_POST['potongan_lain']) ? (float)$_POST['potongan_lain'] : 0.0;
    $gBersih   = ($gPokok + $tJabatan + $tMakan + $tTransp + $uLembur + $uReimb)
               - ($potAlpha + $potBpjsTK + $potBpjsKes + $potPph + $potLain);
    $meId = (int)$me['id'];

    $check = db()->query("SELECT id FROM slip_gaji WHERE user_id=$uid AND bulan=$bulan AND tahun=$tahun");
    if ($check && $check->num_rows > 0) {
        $sid = (int)$check->fetch_assoc()['id'];
        db()->query("UPDATE slip_gaji SET
            gaji_pokok=$gPokok, tunjangan_jabatan=$tJabatan,
            tunjangan_makan=$tMakan, tunjangan_transport=$tTransp,
            upah_lembur=$uLembur, potongan_absen=$potAlpha,
            potongan_bpjs_tk=$potBpjsTK, potongan_bpjs_kes=$potBpjsKes,
            potongan_pph21=$potPph, potongan_lain=$potLain,
            gaji_bersih=$gBersih, hari_kerja=$hKerja, hari_hadir=$hHadir,
            hari_alpha=$hAlpha, total_lembur_jam=$jamLembur,
            dibuat_oleh=$meId, status='draft'
            WHERE id=$sid");
    } else {
        db()->query("INSERT INTO slip_gaji
            (user_id,bulan,tahun,gaji_pokok,tunjangan_jabatan,tunjangan_makan,
             tunjangan_transport,upah_lembur,potongan_absen,potongan_bpjs_tk,
             potongan_bpjs_kes,potongan_pph21,potongan_lain,gaji_bersih,
             hari_kerja,hari_hadir,hari_alpha,total_lembur_jam,dibuat_oleh)
            VALUES
            ($uid,$bulan,$tahun,$gPokok,$tJabatan,$tMakan,
             $tTransp,$uLembur,$potAlpha,$potBpjsTK,
             $potBpjsKes,$potPph,$potLain,$gBersih,
             $hKerja,$hHadir,$hAlpha,$jamLembur,$meId)");
    }
    if (db()->error) flash('error', 'DB Error: '.db()->error);
    else flash('success', 'Slip gaji berhasil di-generate.');
    redirect(BASE_URL.'/pages/admin/penggajian.php?bulan='.$bulan.'&tahun='.$tahun);
}

// ── Atur gaji pokok per karyawan ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atur_gaji'])) {
    $uid    = (int)$_POST['user_id'];
    $gOvr   = strlen(trim($_POST['gaji_pokok_override']       ?? '')) > 0 ? (float)$_POST['gaji_pokok_override']       : 'NULL';
    $tOvr   = strlen(trim($_POST['tunjangan_jabatan_override'] ?? '')) > 0 ? (float)$_POST['tunjangan_jabatan_override'] : 'NULL';
    db()->query("UPDATE users SET gaji_pokok_override=$gOvr, tunjangan_jabatan_override=$tOvr WHERE id=$uid");
    flash('success', 'Pengaturan gaji disimpan.');
    redirect(BASE_URL.'/pages/admin/penggajian.php');
}

// ── Bayar ─────────────────────────────────────────────────────
if (isset($_GET['bayar'])) {
    $sid = (int)$_GET['bayar'];
    db()->query("UPDATE slip_gaji SET status='dibayar',tanggal_bayar=CURDATE() WHERE id=$sid");
    flash('success','Gaji ditandai sudah dibayar.');
    redirect(BASE_URL.'/pages/admin/penggajian.php');
}

// ── List ──────────────────────────────────────────────────────
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
$karList   = db()->query("SELECT u.id,u.nip,u.nama,u.gaji_pokok_override,u.tunjangan_jabatan_override,
    j.gaji_pokok gj_pokok,j.tunjangan_jabatan gj_tunj
    FROM users u LEFT JOIN jabatan j ON u.jabatan_id=j.id
    WHERE u.role='karyawan' AND u.status='aktif' ORDER BY u.nama")->fetch_all(MYSQLI_ASSOC);

$pageTitle     = 'Penggajian';
$activePage    = 'penggajian';
$topbarActions = '<button class="btn btn-sm" onclick="openModal(\'mAturGaji\')">⚙ Atur Gaji</button> <button class="btn btn-primary" onclick="openModal(\'mGen\')">+ Generate Slip</button>';
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
        Belum ada slip gaji. Klik "+ Generate Slip" untuk membuat.
    </td></tr>
    <?php else: while ($r = $rows->fetch_assoc()):
        $tunj = (float)$r['tunjangan_jabatan']+(float)$r['tunjangan_makan']+(float)$r['tunjangan_transport'];
        $pot  = (float)$r['potongan_absen']+(float)$r['potongan_bpjs_tk']+(float)$r['potongan_bpjs_kes']+(float)$r['potongan_pph21']+(float)$r['potongan_lain'];
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

<!-- Modal Generate Slip -->
<div class="modal-overlay" id="mGen">
<div class="modal modal-lg">
    <div class="modal-header"><span class="modal-title">Generate Slip Gaji</span><button class="modal-close" onclick="closeModal('mGen')">✕</button></div>
    <form method="POST">
    <input type="hidden" name="generate" value="1">
    <div class="modal-body">
        <div class="form-grid">
            <div class="form-group form-full">
                <label class="form-label">Karyawan *</label>
                <select name="user_id" id="gen_uid" class="form-control" required onchange="loadGajiKaryawan(this.value)">
                    <option value="">— Pilih karyawan —</option>
                    <?php foreach ($karList as $k): ?>
                    <option value="<?= $k['id'] ?>"
                        data-gaji="<?= $k['gaji_pokok_override'] ?? $k['gj_pokok'] ?>"
                        data-tunj="<?= $k['tunjangan_jabatan_override'] ?? $k['gj_tunj'] ?>">
                        <?= htmlspecialchars($k['nama']) ?> (<?= $k['nip'] ?>)
                    </option>
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

        <div style="margin-top:1rem;padding:1rem;background:var(--surface-2);border-radius:var(--r);border:1px solid var(--border)">
            <div style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">Komponen Gaji (HR dapat edit)</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Gaji Pokok</label>
                    <input type="number" name="gaji_pokok" id="gen_gaji" class="form-control" min="0" placeholder="Auto dari jabatan">
                </div>
                <div class="form-group">
                    <label class="form-label">Tunjangan Jabatan</label>
                    <input type="number" name="tunjangan_jabatan" id="gen_tunj" class="form-control" min="0" placeholder="Auto dari jabatan">
                </div>
                <div class="form-group">
                    <label class="form-label">Tunjangan Makan</label>
                    <input type="number" name="tunjangan_makan" class="form-control" value="750000" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Tunjangan Transport</label>
                    <input type="number" name="tunjangan_transport" class="form-control" value="500000" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Potongan Lain-lain</label>
                    <input type="number" name="potongan_lain" class="form-control" value="0" min="0">
                </div>
            </div>
        </div>
        <div class="alert alert-info mt-1" style="font-size:12px">
            Sistem otomatis menghitung kehadiran, lembur, reimburse, dan BPJS dari data yang ada.
            <br>Kosongkan field gaji/tunjangan agar sistem mengambil dari pengaturan jabatan karyawan.
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('mGen')">Batal</button>
        <button type="submit" class="btn btn-primary">Generate</button>
    </div>
    </form>
</div>
</div>

<!-- Modal Atur Gaji Karyawan -->
<div class="modal-overlay" id="mAturGaji">
<div class="modal modal-lg">
    <div class="modal-header"><span class="modal-title">⚙ Atur Gaji Karyawan</span><button class="modal-close" onclick="closeModal('mAturGaji')">✕</button></div>
    <form method="POST">
    <input type="hidden" name="atur_gaji" value="1">
    <div class="modal-body">
        <div class="form-group mb-2">
            <label class="form-label">Pilih Karyawan *</label>
            <select name="user_id" id="ag_uid" class="form-control" required onchange="loadGajiAtur(this.value)">
                <option value="">— Pilih karyawan —</option>
                <?php foreach ($karList as $k): ?>
                <option value="<?= $k['id'] ?>"
                    data-gaji-ovr="<?= $k['gaji_pokok_override'] ?>"
                    data-tunj-ovr="<?= $k['tunjangan_jabatan_override'] ?>"
                    data-gaji-def="<?= $k['gj_pokok'] ?>"
                    data-tunj-def="<?= $k['gj_tunj'] ?>">
                    <?= htmlspecialchars($k['nama']) ?> (<?= $k['nip'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="ag_info" style="display:none;padding:10px;background:var(--surface-2);border-radius:var(--r);margin-bottom:1rem;font-size:12px;color:var(--text-2)"></div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Gaji Pokok Override <span class="form-hint">(kosong = pakai jabatan)</span></label>
                <input type="number" name="gaji_pokok_override" id="ag_gaji" class="form-control" min="0" placeholder="Kosong = dari jabatan">
            </div>
            <div class="form-group">
                <label class="form-label">Tunjangan Jabatan Override <span class="form-hint">(kosong = pakai jabatan)</span></label>
                <input type="number" name="tunjangan_jabatan_override" id="ag_tunj" class="form-control" min="0" placeholder="Kosong = dari jabatan">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('mAturGaji')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
    </div>
    </form>
</div>
</div>

<script>
function loadGajiKaryawan(uid) {
    var sel = document.getElementById('gen_uid');
    var opt = sel.options[sel.selectedIndex];
    document.getElementById('gen_gaji').value = opt.dataset.gaji || '';
    document.getElementById('gen_tunj').value = opt.dataset.tunj || '';
}
function loadGajiAtur(uid) {
    var sel  = document.getElementById('ag_uid');
    var opt  = sel.options[sel.selectedIndex];
    var gOvr = opt.dataset.gajiOvr;
    var tOvr = opt.dataset.tunjOvr;
    var gDef = opt.dataset.gajiDef;
    var tDef = opt.dataset.tunjDef;
    document.getElementById('ag_gaji').value = (gOvr && gOvr !== 'NULL') ? gOvr : '';
    document.getElementById('ag_tunj').value = (tOvr && tOvr !== 'NULL') ? tOvr : '';
    document.getElementById('ag_info').style.display = '';
    document.getElementById('ag_info').innerHTML =
        'Gaji dari jabatan: <strong>Rp ' + parseInt(gDef||0).toLocaleString('id-ID') + '</strong> &nbsp;|&nbsp; ' +
        'Tunjangan jabatan: <strong>Rp ' + parseInt(tDef||0).toLocaleString('id-ID') + '</strong>' +
        (gOvr && gOvr !== 'NULL' ? ' &nbsp;<span style="color:#fcd34d">⚠ Override aktif</span>' : '');
}
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
