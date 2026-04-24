<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireLogin();
$user = currentUser();
$uid  = (int)$user['id'];

// Update profil kontak
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $telp  = sanitize($_POST['telepon']    ?? '');
    $alamat= sanitize($_POST['alamat']     ?? '');
    $noRek = sanitize($_POST['no_rekening']?? '');
    $bank  = sanitize($_POST['nama_bank']  ?? '');
    $telp_e=esc($telp); $alamat_e=esc($alamat); $noRek_e=esc($noRek); $bank_e=esc($bank);
    db()->query("UPDATE users SET telepon='$telp_e',alamat='$alamat_e',no_rekening='$noRek_e',nama_bank='$bank_e' WHERE id=$uid");
    clearUserCache(); // ← wajib setelah update agar currentUser() reload data
    flash('success','Profil berhasil diperbarui.');
    redirect(BASE_URL.'/pages/karyawan/profil.php');
}

// Ganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ganti_pass'])) {
    $old = $_POST['old_pass'] ?? '';
    $new = $_POST['new_pass'] ?? '';
    $kon = $_POST['konfirmasi']?? '';
    if (!password_verify($old, $user['password'])) {
        flash('error','Password lama salah.');
    } elseif (strlen($new) < 8) {
        flash('error','Password baru minimal 8 karakter.');
    } elseif ($new !== $kon) {
        flash('error','Konfirmasi password tidak cocok.');
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>10]);
        $hash_e = esc($hash);
        db()->query("UPDATE users SET password='$hash_e' WHERE id=$uid");
        clearUserCache();
        flash('success','Password berhasil diubah.');
    }
    redirect(BASE_URL.'/pages/karyawan/profil.php');
}

// Statistik bulan ini
$bulan = (int)date('m'); $tahun = (int)date('Y');
$stat  = db()->query("SELECT SUM(status_kehadiran='hadir') hadir, SUM(status_masuk='terlambat') terlambat FROM absensi WHERE user_id=$uid AND MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun")->fetch_assoc();
$lemJam= (float)db()->query("SELECT COALESCE(SUM(durasi_menit),0)/60 j FROM lembur WHERE user_id=$uid AND MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun AND status='disetujui'")->fetch_assoc()['j'];

$pageTitle  = 'Profil Saya';
$activePage = 'profil';
include __DIR__.'/../../includes/header.php';
?>

<div class="grid-2" style="grid-template-columns:300px 1fr;align-items:start">

    <!-- Kartu profil -->
    <div>
        <div class="card mb-2" style="text-align:center">
            <div class="card-body">
                <div class="avatar av-xl" style="background:<?=avatarBg($uid)?>;margin:0 auto 12px"><?=initials($user['nama'])?></div>
                <div style="font-size:18px;font-weight:700"><?=htmlspecialchars($user['nama'])?></div>
                <div class="mono text-sm text-muted"><?=$user['nip']?></div>
                <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin:10px 0">
                    <?php $bs=['aktif'=>'badge-green','nonaktif'=>'badge-gray','cuti'=>'badge-amber'];?>
                    <span class="badge <?=$bs[$user['status']]??'badge-gray'?>"><?=ucfirst($user['status'])?></span>
                    <span class="badge badge-blue">Cuti: <?=(int)$user['sisa_cuti']?> hari</span>
                </div>
                <table style="width:100%;text-align:left;font-size:13px">
                    <?php $rows=[['Email',$user['email']??'—'],['Departemen',$user['dept_nama']??'—'],['Jabatan',$user['jabatan_nama']??'—'],['Shift',$user['shift_nama']??'—'],['Bergabung',formatTgl($user['tanggal_bergabung']??'')],['Kelamin',$user['jenis_kelamin']==='L'?'Laki-laki':'Perempuan'],['Lahir',formatTgl($user['tanggal_lahir']??'')]];
                    foreach ($rows as [$k,$v]): ?>
                    <tr><td style="color:var(--text-m);padding:5px 0;font-size:12px"><?=$k?></td><td style="padding:5px 0"><?=htmlspecialchars($v)?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Statistik bulan ini -->
        <div class="card">
            <div class="card-header"><span class="card-title">Statistik <?=bulanNama($bulan)?></span></div>
            <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;text-align:center">
                <?php foreach ([['Hadir',(int)$stat['hadir'],'var(--green-400)'],['Terlambat',(int)$stat['terlambat'],'var(--amber)'],['Jam Lembur',round($lemJam,1),'var(--blue)','grid-column:1/-1']] as $s): ?>
                <div style="padding:12px;background:var(--surface-2);border-radius:var(--r);<?=$s[3]??''?>">
                    <div style="font-size:22px;font-weight:700;color:<?=$s[2]?>"><?=$s[1]?></div>
                    <div class="text-xs text-muted"><?=$s[0]?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Edit & Password -->
    <div>
        <div class="card mb-2">
            <div class="card-header"><span class="card-title">Edit Informasi Kontak</span></div>
            <form method="POST">
            <input type="hidden" name="update_profil" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Telepon</label>
                        <input type="text" name="telepon" class="form-control" value="<?=htmlspecialchars($user['telepon']??'')?>"></div>
                    <div class="form-group"><label class="form-label">Nama Bank</label>
                        <input type="text" name="nama_bank" class="form-control" value="<?=htmlspecialchars($user['nama_bank']??'')?>" placeholder="BCA, Mandiri..."></div>
                    <div class="form-group"><label class="form-label">No. Rekening</label>
                        <input type="text" name="no_rekening" class="form-control mono" value="<?=htmlspecialchars($user['no_rekening']??'')?>"></div>
                    <div class="form-group"><label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="2"><?=htmlspecialchars($user['alamat']??'')?></textarea></div>
                </div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Ganti Password</span></div>
            <form method="POST">
            <input type="hidden" name="ganti_pass" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-full"><label class="form-label">Password Lama *</label>
                        <input type="password" name="old_pass" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Password Baru * (min. 8 karakter)</label>
                        <input type="password" name="new_pass" class="form-control" required minlength="8"></div>
                    <div class="form-group"><label class="form-label">Konfirmasi Password *</label>
                        <input type="password" name="konfirmasi" class="form-control" required></div>
                </div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-danger">Ganti Password</button>
            </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
