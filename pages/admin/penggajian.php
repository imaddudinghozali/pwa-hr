<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();
$me = currentUser();

// ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $uid   = (int)$_POST['user_id'];
    $bulan = (int)$_POST['bulan'];
    $tahun = (int)$_POST['tahun'];

    if (!$uid || !$bulan || !$tahun) {
        flash('error','Karyawan, bulan, dan tahun wajib diisi.');
        redirect(BASE_URL.'/pages/admin/penggajian.php');
    }

    $u = db()->query("SELECT id FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();
    if (!$u) {
        flash('error','Karyawan tidak ditemukan.');
        redirect(BASE_URL.'/pages/admin/penggajian.php');
    }

    // Semua nilai diinput manual oleh HR &mdash; tidak ada perhitungan otomatis
    $gPokok    = max(0, (float)($_POST['gaji_pokok']           ?? 0));
    $tJabatan  = max(0, (float)($_POST['tunjangan_jabatan']    ?? 0));
    $tMakan    = max(0, (float)($_POST['tunjangan_makan']      ?? 0));
    $tTransp   = max(0, (float)($_POST['tunjangan_transport']  ?? 0));
    $bonus     = max(0, (float)($_POST['bonus']                ?? 0));
    $uLembur   = max(0, (float)($_POST['upah_lembur']          ?? 0));
    $potAbsen  = max(0, (float)($_POST['potongan_absen']       ?? 0));
    $potBpjsTK = max(0, (float)($_POST['potongan_bpjs_tk']     ?? 0));
    $potBpjsKes= max(0, (float)($_POST['potongan_bpjs_kes']    ?? 0));
    $potPph    = max(0, (float)($_POST['potongan_pph21']       ?? 0));
    $potLain   = max(0, (float)($_POST['potongan_lain']        ?? 0));
    $hKerja    = max(0, (int)($_POST['hari_kerja']             ?? 0));
    $hHadir    = max(0, (int)($_POST['hari_hadir']             ?? 0));
    $hAlpha    = max(0, (int)($_POST['hari_alpha']             ?? 0));
    $jamLembur = max(0, (float)($_POST['total_lembur_jam']     ?? 0));
    $catatan   = sanitize($_POST['catatan'] ?? '');
    $catatan_e = esc($catatan);

    // Hitung gaji bersih = pemasukan - potongan (sederhana, tidak ada formula tersembunyi)
    $totMasuk  = $gPokok + $tJabatan + $tMakan + $tTransp + $bonus + $uLembur;
    $totPot    = $potAbsen + $potBpjsTK + $potBpjsKes + $potPph + $potLain;
    $gBersih   = $totMasuk - $totPot;

    $meId = (int)$me['id'];
    $check = db()->query("SELECT id FROM slip_gaji WHERE user_id=$uid AND bulan=$bulan AND tahun=$tahun");
    if ($check && $check->num_rows > 0) {
        $sid = (int)$check->fetch_assoc()['id'];
        db()->query("UPDATE slip_gaji SET
            gaji_pokok=$gPokok, tunjangan_jabatan=$tJabatan,
            tunjangan_makan=$tMakan, tunjangan_transport=$tTransp,
            bonus=$bonus, upah_lembur=$uLembur,
            potongan_absen=$potAbsen, potongan_bpjs_tk=$potBpjsTK,
            potongan_bpjs_kes=$potBpjsKes, potongan_pph21=$potPph,
            potongan_lain=$potLain, gaji_bersih=$gBersih,
            hari_kerja=$hKerja, hari_hadir=$hHadir, hari_alpha=$hAlpha,
            total_lembur_jam=$jamLembur, dibuat_oleh=$meId, catatan='$catatan_e', status='draft'
            WHERE id=$sid");
    } else {
        db()->query("INSERT INTO slip_gaji
            (user_id,bulan,tahun,gaji_pokok,tunjangan_jabatan,tunjangan_makan,
             tunjangan_transport,bonus,upah_lembur,potongan_absen,potongan_bpjs_tk,
             potongan_bpjs_kes,potongan_pph21,potongan_lain,gaji_bersih,
             hari_kerja,hari_hadir,hari_alpha,total_lembur_jam,dibuat_oleh,catatan)
            VALUES
            ($uid,$bulan,$tahun,$gPokok,$tJabatan,$tMakan,
             $tTransp,$bonus,$uLembur,$potAbsen,$potBpjsTK,
             $potBpjsKes,$potPph,$potLain,$gBersih,
             $hKerja,$hHadir,$hAlpha,$jamLembur,$meId,'$catatan_e')");
    }
    if (db()->error) flash('error', 'DB Error: '.db()->error);
    else flash('success', 'Slip gaji berhasil disimpan (semua nilai input manual oleh HR).');
    redirect(BASE_URL.'/pages/admin/penggajian.php?bulan='.$bulan.'&tahun='.$tahun);
}

// ---
if (isset($_GET['bayar'])) {
    $sid = (int)$_GET['bayar'];
    db()->query("UPDATE slip_gaji SET status='dibayar',tanggal_bayar=CURDATE() WHERE id=$sid");
    flash('success','Gaji ditandai sudah dibayar.');
    redirect(BASE_URL.'/pages/admin/penggajian.php');
}

// ---
if (isset($_GET['hapus'])) {
    $sid = (int)$_GET['hapus'];
    db()->query("DELETE FROM slip_gaji WHERE id=$sid");
    flash('success','Slip gaji dihapus.');
    redirect(BASE_URL.'/pages/admin/penggajian.php');
}

// ---
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
$karList   = db()->query("SELECT u.id,u.nip,u.nama,d.nama dept_nama,j.nama jabatan_nama
    FROM users u
    LEFT JOIN departemen d ON u.departemen_id=d.id
    LEFT JOIN jabatan j ON u.jabatan_id=j.id
    WHERE u.role IN ('karyawan','hrd') AND u.status='aktif' ORDER BY u.nama")->fetch_all(MYSQLI_ASSOC);

$pageTitle     = 'Penggajian';
$activePage    = 'penggajian';
$topbarActions = '<button class="btn btn-primary icon-label" onclick="openModal(\'mGen\')"><span class="ui-icon i-wallet"></span> Buat / Edit Slip Gaji</button>';
include __DIR__.'/../../includes/header.php';
?>

<div class="alert alert-info mb-2 icon-label" style="font-size:12.5px">
    <span class="ui-icon i-info"></span> <span><strong>Penggajian Full Manual</strong> &mdash; HR menginput semua komponen gaji (pokok, tunjangan, bonus, lembur, potongan) secara manual. Sistem hanya menjumlahkan total tanpa perhitungan otomatis.</span>
</div>

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
        <th>Karyawan</th><th>Gaji Pokok</th><th>Tunjangan</th><th>Bonus</th><th>Lembur</th>
        <th>Potongan</th><th>Gaji Bersih</th><th>Status</th><th style="width:200px">Aksi</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows || $rows->num_rows===0): ?>
    <tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--text-m)">
        Belum ada slip gaji. Klik "+ Buat / Edit Slip Gaji" untuk membuat.
    </td></tr>
    <?php else: while ($r = $rows->fetch_assoc()):
        $tunj = (float)$r['tunjangan_jabatan']+(float)$r['tunjangan_makan']+(float)$r['tunjangan_transport'];
        $pot  = (float)$r['potongan_absen']+(float)$r['potongan_bpjs_tk']+(float)$r['potongan_bpjs_kes']+(float)$r['potongan_pph21']+(float)$r['potongan_lain'];
        $bsSt = ['draft'=>'badge-amber','final'=>'badge-blue','dibayar'=>'badge-green'];
        $rJson = json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
    ?>
    <tr>
        <td><div class="name-cell">
            <div class="avatar av-sm" style="background:<?= avatarBg((int)$r['user_id']) ?>"><?= initials($r['nama']) ?></div>
            <div><div class="nc-name"><?= htmlspecialchars($r['nama']) ?></div>
            <div class="nc-sub"><?= htmlspecialchars($r['dept_nama'] ?? '&mdash;') ?></div></div>
        </div></td>
        <td class="mono text-sm"><?= formatRp($r['gaji_pokok']) ?></td>
        <td class="mono text-sm text-green"><?= formatRp($tunj) ?></td>
        <td class="mono text-sm"><?= formatRp((float)($r['bonus'] ?? 0)) ?></td>
        <td class="mono text-sm text-blue"><?= formatRp($r['upah_lembur']) ?></td>
        <td class="mono text-sm text-red"><?= formatRp($pot) ?></td>
        <td class="mono fw-700"><?= formatRp($r['gaji_bersih']) ?></td>
        <td><span class="badge <?= $bsSt[$r['status']] ?? 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
        <td><div class="flex gap-2">
            <a href="<?= BASE_URL ?>/pages/admin/slip_detail.php?id=<?= $r['id'] ?>" class="btn btn-sm">Lihat</a>
            <button class="btn btn-sm" onclick='editSlip(<?= $rJson ?>)'>Edit</button>
            <?php if ($r['status'] !== 'dibayar'): ?>
            <a href="?bayar=<?= $r['id'] ?>&bulan=<?=$bulan?>&tahun=<?=$tahun?>" class="btn btn-sm btn-primary"
               onclick="return confirm('Tandai sudah dibayar?')">Bayar</a>
            <?php endif; ?>
            <a href="?hapus=<?= $r['id'] ?>&bulan=<?=$bulan?>&tahun=<?=$tahun?>" class="btn btn-sm btn-danger"
               onclick="return confirm('Hapus slip ini?')" aria-label="Hapus slip"><span class="ui-icon i-x"></span></a>
        </div></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>
</div>
<div class="pagination">
    <span><?= $total ?> slip</span>
    <div class="page-btns">
        <?php if($page>1):?><a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&page=<?=$page-1?>" class="page-btn">&lsaquo;</a><?php endif;?>
        <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++):?><a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&page=<?=$i?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a><?php endfor;?>
        <?php if($page<$pages):?><a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&page=<?=$page+1?>" class="page-btn">&rsaquo;</a><?php endif;?>
    </div>
</div>
</div>

<!-- Modal Buat/Edit Slip Gaji (FULL MANUAL) -->
<div class="modal-overlay" id="mGen">
<div class="modal modal-lg">
    <div class="modal-header">
        <span class="modal-title" id="mGenTitle">Buat Slip Gaji (Manual)</span>
        <button class="modal-close" onclick="closeModal('mGen')" aria-label="Tutup"><span class="ui-icon i-x"></span></button>
    </div>
    <form method="POST">
    <input type="hidden" name="generate" value="1">
    <div class="modal-body">
        <div class="form-grid">
            <div class="form-group form-full">
                <label class="form-label">Karyawan *</label>
                <select name="user_id" id="gen_uid" class="form-control" required>
                    <option value="">&mdash; Pilih karyawan &mdash;</option>
                    <?php foreach ($karList as $k): ?>
                    <option value="<?= $k['id'] ?>">
                        <?= htmlspecialchars($k['nama']) ?> (<?= htmlspecialchars($k['nip']) ?>)
                        <?= !empty($k['jabatan_nama']) ? ' - '.htmlspecialchars($k['jabatan_nama']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="text-xs text-muted" style="margin-top:6px">Nominal tidak otomatis diambil dari master karyawan, absensi, lembur, atau kontrak.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Bulan *</label>
                <select name="bulan" id="gen_bulan" class="form-control" required>
                    <?php for ($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m===$bulan?'selected':''?>><?=bulanNama($m)?></option><?php endfor;?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tahun *</label>
                <select name="tahun" id="gen_tahun" class="form-control" required>
                    <?php for ($y=(int)date('Y');$y>=(int)date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$y===$tahun?'selected':''?>><?=$y?></option><?php endfor;?>
                </select>
            </div>
        </div>

        <div class="section-panel section-panel-success">
            <div class="section-heading"><span class="ui-icon i-wallet"></span> Pemasukan</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Gaji Pokok</label>
                    <input type="number" name="gaji_pokok" id="gen_gaji" class="form-control calc-input" min="0" value="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tunjangan Jabatan</label>
                    <input type="number" name="tunjangan_jabatan" id="gen_tunj" class="form-control calc-input" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Tunjangan Makan</label>
                    <input type="number" name="tunjangan_makan" id="gen_makan" class="form-control calc-input" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Tunjangan Transport</label>
                    <input type="number" name="tunjangan_transport" id="gen_transp" class="form-control calc-input" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Bonus</label>
                    <input type="number" name="bonus" id="gen_bonus" class="form-control calc-input" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Upah Lembur (Manual)</label>
                    <input type="number" name="upah_lembur" id="gen_lembur" class="form-control calc-input" min="0" value="0">
                </div>
            </div>
        </div>

        <div class="section-panel section-panel-danger">
            <div class="section-heading" style="color:#fca5a5"><span class="ui-icon i-wallet"></span> Potongan</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Potongan Absen/Alpha (Manual)</label>
                    <input type="number" name="potongan_absen" id="gen_potAbsen" class="form-control calc-input" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">BPJS Ketenagakerjaan</label>
                    <input type="number" name="potongan_bpjs_tk" id="gen_potBpjsTK" class="form-control calc-input" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">BPJS Kesehatan</label>
                    <input type="number" name="potongan_bpjs_kes" id="gen_potBpjsKes" class="form-control calc-input" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">PPh 21</label>
                    <input type="number" name="potongan_pph21" id="gen_potPph" class="form-control calc-input" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Potongan Lain-lain</label>
                    <input type="number" name="potongan_lain" id="gen_potLain" class="form-control calc-input" min="0" value="0">
                </div>
            </div>
        </div>

        <div class="section-panel section-panel-muted">
            <div class="section-heading"><span class="ui-icon i-chart"></span> Catatan Kehadiran (opsional)</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Hari Kerja</label>
                    <input type="number" name="hari_kerja" id="gen_hKerja" class="form-control" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Hari Hadir</label>
                    <input type="number" name="hari_hadir" id="gen_hHadir" class="form-control" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Hari Alpha</label>
                    <input type="number" name="hari_alpha" id="gen_hAlpha" class="form-control" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Total Lembur (jam)</label>
                    <input type="number" step="0.25" name="total_lembur_jam" id="gen_jamLembur" class="form-control" min="0" value="0">
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Catatan</label>
                    <textarea name="catatan" id="gen_catatan" class="form-control" rows="3" placeholder="Opsional..."></textarea>
                </div>
            </div>
        </div>

        <div class="summary-strip">
            <div>
                <div style="font-size:11px;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px">Gaji Bersih (Pemasukan - Potongan)</div>
                <div id="gen_preview" style="font-size:22px;font-weight:800;color:var(--green-400);margin-top:4px">Rp 0</div>
            </div>
            <div style="text-align:right;font-size:11px;color:var(--text-2)">
                Pemasukan: <span id="gen_totMasuk" style="color:var(--green-400);font-weight:700">Rp 0</span><br>
                Potongan: <span id="gen_totPot" style="color:#fca5a5;font-weight:700">Rp 0</span>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('mGen')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Slip</button>
    </div>
    </form>
</div>
</div>

<script>
function _num(id) { return parseFloat(document.getElementById(id).value) || 0; }
function _fmt(n)  { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

function recalcGaji() {
    var masuk = _num('gen_gaji') + _num('gen_tunj') + _num('gen_makan') +
                _num('gen_transp') + _num('gen_bonus') + _num('gen_lembur');
    var pot   = _num('gen_potAbsen') + _num('gen_potBpjsTK') + _num('gen_potBpjsKes') +
                _num('gen_potPph') + _num('gen_potLain');
    document.getElementById('gen_totMasuk').textContent = _fmt(masuk);
    document.getElementById('gen_totPot').textContent   = _fmt(pot);
    document.getElementById('gen_preview').textContent  = _fmt(masuk - pot);
}
document.querySelectorAll('#mGen .calc-input').forEach(function(i){ i.addEventListener('input', recalcGaji); });

function editSlip(s) {
    document.getElementById('mGenTitle').textContent = 'Edit Slip Gaji';
    document.getElementById('gen_uid').value         = s.user_id;
    document.getElementById('gen_bulan').value       = s.bulan;
    document.getElementById('gen_tahun').value       = s.tahun;
    document.getElementById('gen_gaji').value        = s.gaji_pokok           || 0;
    document.getElementById('gen_tunj').value        = s.tunjangan_jabatan    || 0;
    document.getElementById('gen_makan').value       = s.tunjangan_makan      || 0;
    document.getElementById('gen_transp').value      = s.tunjangan_transport  || 0;
    document.getElementById('gen_bonus').value       = s.bonus                || 0;
    document.getElementById('gen_lembur').value      = s.upah_lembur          || 0;
    document.getElementById('gen_potAbsen').value    = s.potongan_absen       || 0;
    document.getElementById('gen_potBpjsTK').value   = s.potongan_bpjs_tk     || 0;
    document.getElementById('gen_potBpjsKes').value  = s.potongan_bpjs_kes    || 0;
    document.getElementById('gen_potPph').value      = s.potongan_pph21       || 0;
    document.getElementById('gen_potLain').value     = s.potongan_lain        || 0;
    document.getElementById('gen_hKerja').value      = s.hari_kerja           || 0;
    document.getElementById('gen_hHadir').value      = s.hari_hadir           || 0;
    document.getElementById('gen_hAlpha').value      = s.hari_alpha           || 0;
    document.getElementById('gen_jamLembur').value   = s.total_lembur_jam     || 0;
    document.getElementById('gen_catatan').value     = s.catatan              || '';
    recalcGaji();
    openModal('mGen');
}

// Reset modal saat dibuka via tombol "+ Buat"
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.querySelector('[onclick="openModal(\'mGen\')"]');
    if (btn) btn.addEventListener('click', function(){
        document.getElementById('mGenTitle').textContent = 'Buat Slip Gaji (Manual)';
        document.querySelectorAll('#mGen input[type=number]').forEach(function(i){ i.value = 0; });
        document.getElementById('gen_uid').value = '';
        document.getElementById('gen_catatan').value = '';
        recalcGaji();
    });
});
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
