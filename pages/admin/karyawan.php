<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();

// ── POST: tambah / edit ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id      = (int)$_POST['id'];
    $nama    = sanitize($_POST['nama']               ?? '');
    $nip     = sanitize($_POST['nip']                ?? '');
    $email   = sanitize($_POST['email']              ?? '');
    $telp    = sanitize($_POST['telepon']            ?? '');
    $deptId  = (int)($_POST['departemen_id']         ?? 0);
    $jabId   = (int)($_POST['jabatan_id']            ?? 0);
    $shiftId = (int)($_POST['shift_id']              ?? 0);
    $tglJoin = sanitize($_POST['tanggal_bergabung']  ?? '');
    $tglKont = sanitize($_POST['tanggal_kontrak_selesai'] ?? '');
    $tglLhr  = sanitize($_POST['tanggal_lahir']      ?? '');
    $jk      = sanitize($_POST['jenis_kelamin']      ?? 'L');
    $status  = sanitize($_POST['status']             ?? 'aktif');
    $role    = sanitize($_POST['role']               ?? 'karyawan');
    $jenisKar= sanitize($_POST['jenis_karyawan']     ?? 'tetap');
    $alamat  = sanitize($_POST['alamat']             ?? '');
    $noRek   = sanitize($_POST['no_rekening']        ?? '');
    $bank    = sanitize($_POST['nama_bank']          ?? '');
    $gajiOvr = strlen(trim($_POST['gaji_pokok_override'] ?? '')) > 0
                ? (float)$_POST['gaji_pokok_override'] : null;
    $tunjOvr = strlen(trim($_POST['tunjangan_jabatan_override'] ?? '')) > 0
                ? (float)$_POST['tunjangan_jabatan_override'] : null;

    if (!$nama || !$nip) {
        flash('error', 'Nama dan NIP wajib diisi.');
        redirect(BASE_URL.'/pages/admin/karyawan.php');
    }

    $tglKontSql = ($tglKont && $tglKont !== '') ? "'".esc($tglKont)."'" : 'NULL';
    $tglJoinSql = ($tglJoin && $tglJoin !== '') ? "'".esc($tglJoin)."'" : 'NULL';
    $tglLhrSql  = ($tglLhr  && $tglLhr  !== '') ? "'".esc($tglLhr)."'"  : 'NULL';
    $gajiOvrSql = $gajiOvr !== null ? $gajiOvr : 'NULL';
    $tunjOvrSql = $tunjOvr !== null ? $tunjOvr : 'NULL';

    if ($id > 0) {
        $nama_e=esc($nama); $nip_e=esc($nip); $email_e=esc($email); $telp_e=esc($telp);
        $jk_e=esc($jk); $status_e=esc($status); $role_e=esc($role); $alamat_e=esc($alamat);
        $noRek_e=esc($noRek); $bank_e=esc($bank); $jenisKar_e=esc($jenisKar);
        db()->query("UPDATE users SET
            nama='$nama_e', nip='$nip_e', email='$email_e', telepon='$telp_e',
            departemen_id=$deptId, jabatan_id=$jabId, shift_id=$shiftId,
            tanggal_bergabung=$tglJoinSql, tanggal_kontrak_selesai=$tglKontSql,
            tanggal_lahir=$tglLhrSql, jenis_kelamin='$jk_e', status='$status_e',
            role='$role_e', jenis_karyawan='$jenisKar_e',
            alamat='$alamat_e', no_rekening='$noRek_e', nama_bank='$bank_e',
            gaji_pokok_override=$gajiOvrSql, tunjangan_jabatan_override=$tunjOvrSql
            WHERE id=$id");
        if (!empty($_POST['password'])) {
            $hash = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost'=>10]);
            $h_e  = esc($hash);
            db()->query("UPDATE users SET password='$h_e' WHERE id=$id");
        }
        if (db()->error) flash('error', 'Gagal update: '.db()->error);
        else flash('success', "Data $nama diperbarui.");
    } else {
        $pw   = !empty($_POST['password']) ? $_POST['password'] : 'Karyawan@123';
        $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost'=>10]);
        $nama_e=esc($nama); $nip_e=esc($nip); $email_e=esc($email); $telp_e=esc($telp);
        $jk_e=esc($jk); $status_e=esc($status); $role_e=esc($role); $alamat_e=esc($alamat);
        $noRek_e=esc($noRek); $bank_e=esc($bank); $hash_e=esc($hash);
        $jenisKar_e=esc($jenisKar);
        db()->query("INSERT INTO users
            (nama,nip,email,telepon,departemen_id,jabatan_id,shift_id,
             tanggal_bergabung,tanggal_kontrak_selesai,tanggal_lahir,jenis_kelamin,
             status,role,jenis_karyawan,alamat,no_rekening,nama_bank,password,
             gaji_pokok_override,tunjangan_jabatan_override,sisa_cuti)
            VALUES
            ('$nama_e','$nip_e','$email_e','$telp_e',$deptId,$jabId,$shiftId,
             $tglJoinSql,$tglKontSql,$tglLhrSql,'$jk_e','$status_e','$role_e',
             '$jenisKar_e','$alamat_e','$noRek_e','$bank_e','$hash_e',
             $gajiOvrSql,$tunjOvrSql,12)");
        if (db()->error) flash('error', 'Gagal tambah: '.db()->error);
        else {
            // Tambahkan ke grup HR General
            $newId = db()->insert_id;
            $room  = db()->query("SELECT id FROM chat_rooms WHERE tipe='group' LIMIT 1")->fetch_assoc();
            if ($room) db()->query("INSERT IGNORE INTO chat_room_members (room_id,user_id) VALUES ({$room['id']},$newId)");
            flash('success', "Karyawan $nama ditambahkan.");
            $_SESSION['new_pw_info'] = ['nama'=>$nama,'nip'=>$nip,'pw'=>$pw];
        }
    }
    redirect(BASE_URL.'/pages/admin/karyawan.php');
}

// ── DELETE ────────────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $r  = db()->query("SELECT nama FROM users WHERE id=$id")->fetch_assoc();
    if ($r) {
        db()->query("DELETE FROM users WHERE id=$id");
        flash('success', "Karyawan {$r['nama']} dihapus.");
    }
    redirect(BASE_URL.'/pages/admin/karyawan.php');
}

// ── LIST ──────────────────────────────────────────────────────
$q      = sanitize($_GET['q']       ?? '');
$deptF  = (int)($_GET['dept']       ?? 0);
$stF    = sanitize($_GET['status']  ?? '');
$jenisF = sanitize($_GET['jenis']   ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$per    = 12; $off = ($page-1)*$per;

$where = ["u.role IN ('karyawan','hrd')"];
if ($q)      $where[] = "(u.nama LIKE '%".esc($q)."%' OR u.nip LIKE '%".esc($q)."%' OR u.email LIKE '%".esc($q)."%')";
if ($deptF)  $where[] = "u.departemen_id=$deptF";
if ($stF)    $where[] = "u.status='".esc($stF)."'";
if ($jenisF) $where[] = "u.jenis_karyawan='".esc($jenisF)."'";
$w = implode(' AND ', $where);

$total = (int)db()->query("SELECT COUNT(*) c FROM users u WHERE $w")->fetch_assoc()['c'];
$pages = max(1,(int)ceil($total/$per));
$rows  = db()->query("SELECT u.*,d.nama dept_nama,j.nama jabatan_nama,s.nama shift_nama
    FROM users u
    LEFT JOIN departemen d ON u.departemen_id=d.id
    LEFT JOIN jabatan j ON u.jabatan_id=j.id
    LEFT JOIN shift s ON u.shift_id=s.id
    WHERE $w ORDER BY u.nama LIMIT $per OFFSET $off");

// Kontrak akan habis dalam 30 hari
$nearExpiry = db()->query("SELECT u.nama, u.tanggal_kontrak_selesai,
    DATEDIFF(u.tanggal_kontrak_selesai, CURDATE()) sisa_hari
    FROM users u WHERE u.jenis_karyawan='kontrak'
    AND u.tanggal_kontrak_selesai BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)
    AND u.status='aktif' ORDER BY u.tanggal_kontrak_selesai ASC");

$deptList  = getDepartemen();
$jabList   = getJabatan();
$shiftList = db()->query("SELECT * FROM shift ORDER BY nama")->fetch_all(MYSQLI_ASSOC);

$pageTitle      = 'Manajemen Karyawan';
$activePage     = 'karyawan';
$topbarActions  = '<a href="'.BASE_URL.'/pages/admin/export_karyawan.php" class="btn btn-sm">⬇ Export</a> <button class="btn btn-primary" onclick="openModal(\'mTambah\')">+ Tambah</button>';
include __DIR__.'/../../includes/header.php';
?>

<?php if (!empty($_SESSION['new_pw_info'])):
    $npw = $_SESSION['new_pw_info'];
    unset($_SESSION['new_pw_info']); ?>
<div class="alert" style="background:#052e16;border:1px solid #16a34a;color:#bbf7d0;display:flex;align-items:center;gap:12px;flex-wrap:wrap" id="pwBanner">
    <span style="font-size:18px">🔑</span>
    <div style="flex:1">
        <strong>Karyawan <?= htmlspecialchars($npw['nama']) ?> (<?= htmlspecialchars($npw['nip']) ?>) berhasil ditambahkan.</strong><br>
        <span style="font-size:12px">Password login: </span>
        <code id="pwDisplay" style="background:#166534;padding:2px 10px;border-radius:4px;font-size:14px;letter-spacing:1px;cursor:pointer" title="Klik untuk salin" onclick="copyPw()">
            <?= htmlspecialchars($npw['pw']) ?>
        </code>
        <span id="pwCopied" style="display:none;font-size:11px;color:#4ade80;margin-left:6px">✔ Disalin!</span>
        <span style="font-size:11px;opacity:.7;margin-left:8px">Klik password untuk menyalin. Catat dan sampaikan ke karyawan.</span>
    </div>
    <button class="btn btn-sm" style="border-color:#16a34a;color:#bbf7d0" onclick="document.getElementById('pwBanner').remove()">Tutup</button>
</div>
<script>
function copyPw() {
    var t = document.getElementById('pwDisplay').textContent.trim();
    navigator.clipboard.writeText(t).then(function(){ document.getElementById('pwCopied').style.display='inline'; });
}
</script>
<?php endif; ?>

<?php if ($nearExpiry && $nearExpiry->num_rows > 0): ?>
<div class="alert alert-amber" style="display:flex;align-items:center;gap:10px">
    <span style="font-size:18px">⚠</span>
    <div>
        <strong>Kontrak Akan Habis!</strong>
        <?php while ($e = $nearExpiry->fetch_assoc()): ?>
        <span style="margin-left:8px;font-size:12px;background:rgba(245,158,11,.2);padding:2px 8px;border-radius:100px">
            <?= htmlspecialchars($e['nama']) ?> (<?= (int)$e['sisa_hari'] ?> hari)
        </span>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<form method="GET">
<div class="toolbar">
    <div class="toolbar-left">
        <input type="text" name="q" class="search-box" placeholder="Cari nama, NIP, email..." value="<?= htmlspecialchars($q) ?>">
        <select name="dept" class="sel-filter auto-submit">
            <option value="">Semua Dept.</option>
            <?php foreach ($deptList as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $deptF==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['nama']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="sel-filter auto-submit">
            <option value="">Semua Status</option>
            <option value="aktif"    <?= $stF==='aktif'   ?'selected':'' ?>>Aktif</option>
            <option value="cuti"     <?= $stF==='cuti'    ?'selected':'' ?>>Cuti</option>
            <option value="nonaktif" <?= $stF==='nonaktif'?'selected':'' ?>>Non-aktif</option>
        </select>
        <select name="jenis" class="sel-filter auto-submit">
            <option value="">Semua Jenis</option>
            <option value="tetap"   <?= $jenisF==='tetap'  ?'selected':'' ?>>Tetap</option>
            <option value="kontrak" <?= $jenisF==='kontrak'?'selected':'' ?>>Kontrak</option>
            <option value="magang"  <?= $jenisF==='magang' ?'selected':'' ?>>Magang</option>
        </select>
        <?php if ($q||$deptF||$stF||$jenisF): ?><a href="?" class="btn btn-sm">Reset</a><?php endif; ?>
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
        <th>Karyawan</th><th>NIP</th><th>Jenis</th><th>Departemen</th><th>Jabatan</th><th>Shift</th><th>Status</th><th>Kontrak s/d</th><th style="width:130px"></th>
    </tr></thead>
    <tbody>
    <?php if (!$rows || $rows->num_rows===0): ?>
    <tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--text-m)">Tidak ada data karyawan</td></tr>
    <?php else: while ($r = $rows->fetch_assoc()):
        $bs  = ['aktif'=>'badge-green','cuti'=>'badge-amber','nonaktif'=>'badge-gray'];
        $bjk = ['tetap'=>'badge-green','kontrak'=>'badge-amber','magang'=>'badge-blue'];
        $kontrakHabis = false;
        if ($r['jenis_karyawan']==='kontrak' && !empty($r['tanggal_kontrak_selesai'])) {
            $sisa = (int)floor((strtotime($r['tanggal_kontrak_selesai'])-time())/86400);
            $kontrakHabis = $sisa <= 30;
        }
    ?>
    <tr>
        <td><div class="name-cell">
            <div class="avatar av-md" style="background:<?= avatarBg((int)$r['id']) ?>"><?= initials($r['nama']) ?></div>
            <div><div class="nc-name"><?= htmlspecialchars($r['nama']) ?></div>
            <div class="nc-sub"><?= htmlspecialchars($r['email'] ?? '') ?></div></div>
        </div></td>
        <td class="mono text-sm"><?= htmlspecialchars($r['nip']) ?></td>
        <td><span class="badge <?= $bjk[$r['jenis_karyawan']??'tetap'] ?? 'badge-gray' ?>"><?= ucfirst($r['jenis_karyawan']??'tetap') ?></span></td>
        <td class="text-sm"><?= htmlspecialchars($r['dept_nama'] ?? '—') ?></td>
        <td class="text-sm"><?= htmlspecialchars($r['jabatan_nama'] ?? '—') ?></td>
        <td><span class="badge badge-purple text-xs"><?= htmlspecialchars($r['shift_nama'] ?? '—') ?></span></td>
        <td><span class="badge <?= $bs[$r['status']] ?? 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
        <td class="text-sm">
            <?php if ($r['jenis_karyawan']==='kontrak' && !empty($r['tanggal_kontrak_selesai'])): ?>
            <span style="<?= $kontrakHabis?'color:#fcd34d;font-weight:600':'' ?>">
                <?= formatTgl($r['tanggal_kontrak_selesai']) ?>
                <?php if ($kontrakHabis): ?><span style="font-size:10px"> ⚠</span><?php endif; ?>
            </span>
            <?php else: ?>—<?php endif; ?>
        </td>
        <td>
            <div class="flex gap-2">
                <a href="<?= BASE_URL ?>/pages/admin/karyawan_detail.php?id=<?= $r['id'] ?>" class="btn btn-sm">Detail</a>
                <button class="btn btn-sm" onclick='editKar(<?= json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>Edit</button>
                <button class="btn btn-sm btn-danger" onclick="confirmDel('<?= BASE_URL ?>/pages/admin/karyawan.php?hapus=<?= $r['id'] ?>','<?= htmlspecialchars($r['nama'],ENT_QUOTES) ?>')">✕</button>
            </div>
        </td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>
</div>
<div class="pagination">
    <span>Hal <?= $page ?>/<?= $pages ?> · <?= $total ?> total</span>
    <div class="page-btns">
        <?php if($page>1):?><a href="?q=<?=urlencode($q)?>&dept=<?=$deptF?>&status=<?=$stF?>&jenis=<?=$jenisF?>&page=<?=$page-1?>" class="page-btn">‹</a><?php endif;?>
        <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++):?><a href="?q=<?=urlencode($q)?>&dept=<?=$deptF?>&status=<?=$stF?>&jenis=<?=$jenisF?>&page=<?=$i?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a><?php endfor;?>
        <?php if($page<$pages):?><a href="?q=<?=urlencode($q)?>&dept=<?=$deptF?>&status=<?=$stF?>&jenis=<?=$jenisF?>&page=<?=$page+1?>" class="page-btn">›</a><?php endif;?>
    </div>
</div>
</div>

<?php
function formKaryawan(string $modalId, string $title, array $deptList, array $jabList, array $shiftList): void { ?>
<div class="modal-overlay" id="<?= $modalId ?>">
<div class="modal modal-lg">
    <div class="modal-header">
        <span class="modal-title"><?= $title ?></span>
        <button class="modal-close" onclick="closeModal('<?= $modalId ?>')">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="id" id="<?= $modalId ?>_id" value="0">
    <div class="modal-body">
        <div class="form-grid">
            <div class="form-group"><label class="form-label">Nama *</label>
                <input type="text" name="nama" id="<?= $modalId ?>_nama" class="form-control" required></div>
            <div class="form-group"><label class="form-label">NIP *</label>
                <input type="text" name="nip" id="<?= $modalId ?>_nip" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Email</label>
                <input type="email" name="email" id="<?= $modalId ?>_email" class="form-control"></div>
            <div class="form-group"><label class="form-label">Telepon</label>
                <input type="text" name="telepon" id="<?= $modalId ?>_telp" class="form-control"></div>
            <div class="form-group"><label class="form-label">Departemen</label>
                <select name="departemen_id" id="<?= $modalId ?>_dept" class="form-control">
                    <option value="0">— Pilih —</option>
                    <?php foreach ($deptList as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nama']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label class="form-label">Jabatan</label>
                <select name="jabatan_id" id="<?= $modalId ?>_jab" class="form-control">
                    <option value="0">— Pilih —</option>
                    <?php foreach ($jabList as $j): ?>
                    <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label class="form-label">Shift</label>
                <select name="shift_id" id="<?= $modalId ?>_shift" class="form-control">
                    <option value="0">— Pilih —</option>
                    <?php foreach ($shiftList as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nama']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label class="form-label">Role</label>
                <select name="role" id="<?= $modalId ?>_role" class="form-control">
                    <option value="karyawan">Karyawan</option>
                    <option value="hrd">HRD</option>
                    <option value="admin">Admin</option>
                </select></div>
            <div class="form-group"><label class="form-label">Jenis Karyawan</label>
                <select name="jenis_karyawan" id="<?= $modalId ?>_jenis" class="form-control" onchange="toggleKontrak('<?= $modalId ?>')">
                    <option value="tetap">Tetap</option>
                    <option value="kontrak">Kontrak</option>
                    <option value="magang">Magang</option>
                </select></div>
            <div class="form-group"><label class="form-label">Tgl Bergabung</label>
                <input type="date" name="tanggal_bergabung" id="<?= $modalId ?>_tgljoin" class="form-control"></div>
            <div class="form-group" id="<?= $modalId ?>_kontrak_wrap" style="display:none">
                <label class="form-label">Tgl Kontrak Selesai</label>
                <input type="date" name="tanggal_kontrak_selesai" id="<?= $modalId ?>_tglkont" class="form-control"></div>
            <div class="form-group"><label class="form-label">Tgl Lahir</label>
                <input type="date" name="tanggal_lahir" id="<?= $modalId ?>_tgllhr" class="form-control"></div>
            <div class="form-group"><label class="form-label">Jenis Kelamin</label>
                <select name="jenis_kelamin" id="<?= $modalId ?>_jk" class="form-control">
                    <option value="L">Laki-laki</option><option value="P">Perempuan</option>
                </select></div>
            <div class="form-group"><label class="form-label">Status</label>
                <select name="status" id="<?= $modalId ?>_status" class="form-control">
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Non-aktif</option>
                </select></div>
            <div class="form-group"><label class="form-label">No. Rekening</label>
                <input type="text" name="no_rekening" id="<?= $modalId ?>_norek" class="form-control"></div>
            <div class="form-group"><label class="form-label">Nama Bank</label>
                <input type="text" name="nama_bank" id="<?= $modalId ?>_bank" class="form-control"></div>
            <div class="form-group">
                <label class="form-label">Override Gaji Pokok <span class="form-hint">(kosong = dari jabatan)</span></label>
                <input type="number" name="gaji_pokok_override" id="<?= $modalId ?>_gajiOvr" class="form-control" min="0" placeholder="Isi untuk override"></div>
            <div class="form-group">
                <label class="form-label">Override Tunjangan Jabatan <span class="form-hint">(kosong = dari jabatan)</span></label>
                <input type="number" name="tunjangan_jabatan_override" id="<?= $modalId ?>_tunjOvr" class="form-control" min="0" placeholder="Isi untuk override"></div>
            <div class="form-group form-full"><label class="form-label">Alamat</label>
                <textarea name="alamat" id="<?= $modalId ?>_alamat" class="form-control" rows="2"></textarea></div>
            <div class="form-group form-full">
                <label class="form-label">Password <?= $modalId==='mTambah'?'(kosong = Karyawan@123)':'(kosong = tidak diubah)' ?></label>
                <input type="password" name="password" id="<?= $modalId ?>_pw" class="form-control"
                    autocomplete="new-password" placeholder="Biarkan kosong untuk default"></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('<?= $modalId ?>')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
    </div>
    </form>
</div>
</div>
<?php } ?>

<?php formKaryawan('mTambah','Tambah Karyawan Baru',$deptList,$jabList,$shiftList); ?>
<?php formKaryawan('mEdit','Edit Karyawan',$deptList,$jabList,$shiftList); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.querySelector('[onclick="openModal(\'mTambah\')"]');
    if (btn) btn.addEventListener('click', function() {
        document.getElementById('mTambah_pw').value = '';
    });
});
function toggleKontrak(m) {
    var v = document.getElementById(m+'_jenis').value;
    document.getElementById(m+'_kontrak_wrap').style.display = (v === 'kontrak' || v === 'magang') ? '' : 'none';
}
function editKar(d) {
    var m = 'mEdit';
    document.getElementById(m+'_pw').value     = '';
    document.getElementById(m+'_id').value     = d.id;
    document.getElementById(m+'_nama').value   = d.nama;
    document.getElementById(m+'_nip').value    = d.nip;
    document.getElementById(m+'_email').value  = d.email || '';
    document.getElementById(m+'_telp').value   = d.telepon || '';
    document.getElementById(m+'_dept').value   = d.departemen_id || 0;
    document.getElementById(m+'_jab').value    = d.jabatan_id || 0;
    document.getElementById(m+'_shift').value  = d.shift_id || 0;
    document.getElementById(m+'_role').value   = d.role || 'karyawan';
    document.getElementById(m+'_jenis').value  = d.jenis_karyawan || 'tetap';
    document.getElementById(m+'_tgljoin').value= d.tanggal_bergabung || '';
    document.getElementById(m+'_tglkont').value= d.tanggal_kontrak_selesai || '';
    document.getElementById(m+'_tgllhr').value = d.tanggal_lahir || '';
    document.getElementById(m+'_jk').value     = d.jenis_kelamin || 'L';
    document.getElementById(m+'_status').value = d.status || 'aktif';
    document.getElementById(m+'_norek').value  = d.no_rekening || '';
    document.getElementById(m+'_bank').value   = d.nama_bank || '';
    document.getElementById(m+'_alamat').value = d.alamat || '';
    document.getElementById(m+'_gajiOvr').value  = d.gaji_pokok_override || '';
    document.getElementById(m+'_tunjOvr').value  = d.tunjangan_jabatan_override || '';
    toggleKontrak(m);
    openModal(m);
}
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
