<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireLogin();
$user = currentUser();
if ($user['role'] !== 'karyawan') redirect(BASE_URL.'/pages/admin/absensi.php');

$uid  = (int)$user['id'];
$hari = date('Y-m-d');

// Pastikan folder upload ada
$uploadDir = __DIR__.'/../../assets/uploads/absensi/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ── POST: absen masuk / keluar ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipe   = sanitize($_POST['tipe']    ?? '');
    $lat    = (float)($_POST['lat']      ?? 0);
    $lng    = (float)($_POST['lng']      ?? 0);
    $device = sanitize(substr($_POST['device'] ?? '', 0, 200));
    $foto64 = $_POST['foto_base64'] ?? '';

    if (!$lat || !$lng) {
        flash('error', 'Koordinat GPS tidak valid. Aktifkan GPS dan coba lagi.');
        redirect(BASE_URL.'/pages/karyawan/absensi.php');
    }

    $offLat = (float)($user['lokasi_lat'] ?: KANTOR_LAT);
    $offLng = (float)($user['lokasi_lng'] ?: KANTOR_LNG);
    $radius = (int)($user['radius_absen'] ?: ABSEN_RADIUS);
    $jarak  = (int)round(hitungJarak($lat, $lng, $offLat, $offLng));

    if ($jarak > $radius) {
        flash('error', "Lokasi Anda {$jarak}m dari kantor. Batas absensi: {$radius}m.");
        redirect(BASE_URL.'/pages/karyawan/absensi.php');
    }

    // Simpan foto selfie
    $namaFoto = '';
    if ($foto64 && strpos($foto64, 'data:image/jpeg;base64,') === 0) {
        $imageData = base64_decode(str_replace('data:image/jpeg;base64,', '', $foto64));
        $namaFoto  = 'absen_'.$uid.'_'.date('Ymd_His').'_'.$tipe.'.jpg';
        @file_put_contents($uploadDir.$namaFoto, $imageData);
    }

    $now      = date('Y-m-d H:i:s');
    $nowTime  = date('H:i:s');
    $absenAda = db()->query("SELECT * FROM absensi WHERE user_id=$uid AND tanggal='$hari' LIMIT 1")->fetch_assoc();
    $shiftId  = !empty($user['shift_id']) ? (int)$user['shift_id'] : null;
    $namaFoto_e = esc($namaFoto);

    if ($tipe === 'masuk') {
        if ($absenAda) {
            flash('error', 'Anda sudah absen masuk hari ini.');
            redirect(BASE_URL.'/pages/karyawan/absensi.php');
        }
        $shiftMasuk  = $user['jam_masuk'] ?? '08:00:00';
        $toleransi   = (int)($user['toleransi_terlambat'] ?? 15);
        $batasMasuk  = date('H:i:s', strtotime($shiftMasuk) + $toleransi * 60);
        $statusMasuk = ($nowTime > $batasMasuk) ? 'terlambat' : 'tepat';

        $shiftSql = $shiftId ? $shiftId : 'NULL';
        $lat_e    = esc($lat); $lng_e = esc($lng);
        $now_e    = esc($now); $dev_e = esc($device);
        $stm_e    = esc($statusMasuk);
        db()->query("INSERT INTO absensi
            (user_id,tanggal,shift_id,jam_masuk,lat_masuk,lng_masuk,jarak_masuk,
             status_masuk,status_kehadiran,foto_masuk,device_info)
            VALUES ($uid,'$hari',$shiftSql,'$now_e',$lat_e,$lng_e,$jarak,
             '$stm_e','hadir','$namaFoto_e','$dev_e')");

        if (db()->error) flash('error', 'Gagal menyimpan absensi: '.db()->error);
        else {
            $msg = $statusMasuk === 'terlambat'
                ? "Absen masuk — TERLAMBAT (jarak: {$jarak}m)"
                : "Absen masuk berhasil — Tepat waktu (jarak: {$jarak}m)";
            flash($statusMasuk === 'terlambat' ? 'amber' : 'success', $msg);
        }

    } elseif ($tipe === 'keluar') {
        if (!$absenAda) { flash('error', 'Belum absen masuk hari ini.'); redirect(BASE_URL.'/pages/karyawan/absensi.php'); }
        if ($absenAda['jam_keluar']) { flash('error', 'Sudah absen keluar hari ini.'); redirect(BASE_URL.'/pages/karyawan/absensi.php'); }

        $lat_e = esc($lat); $lng_e = esc($lng);
        $now_e = esc($now);
        db()->query("UPDATE absensi SET
            jam_keluar='$now_e', lat_keluar=$lat_e, lng_keluar=$lng_e,
            jarak_keluar=$jarak, foto_keluar='$namaFoto_e'
            WHERE user_id=$uid AND tanggal='$hari'");
        flash('success', "Absen keluar berhasil (jarak: {$jarak}m). Sampai jumpa!");
    }
    redirect(BASE_URL.'/pages/karyawan/absensi.php');
}

// ── Data ─────────────────────────────────────────────────────
$absenHari = db()->query("SELECT * FROM absensi WHERE user_id=$uid AND tanggal='$hari' LIMIT 1")->fetch_assoc();
$bulan     = (int)($_GET['bulan'] ?? date('m'));
$tahun     = (int)($_GET['tahun'] ?? date('Y'));
$riwayat   = db()->query("SELECT * FROM absensi WHERE user_id=$uid AND MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun ORDER BY tanggal DESC");
$rekap     = db()->query("SELECT COUNT(*) total, SUM(status_kehadiran='hadir') hadir, SUM(status_masuk='terlambat') terlambat, SUM(status_kehadiran='alpha') alpha
    FROM absensi WHERE user_id=$uid AND MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun")->fetch_assoc();

$offLat = (float)($user['lokasi_lat'] ?: KANTOR_LAT);
$offLng = (float)($user['lokasi_lng'] ?: KANTOR_LNG);
$radius = (int)($user['radius_absen'] ?: ABSEN_RADIUS);

$pageTitle  = 'Absensi Kamera';
$pageSub    = date('l, d F Y');
$activePage = 'absensi';
include __DIR__.'/../../includes/header.php';
?>

<input type="hidden" id="office-lat"   value="<?= $offLat ?>">
<input type="hidden" id="office-lng"   value="<?= $offLng ?>">
<input type="hidden" id="absen-radius" value="<?= $radius ?>">

<div class="grid-2 mb-2" style="grid-template-columns:1fr 1.5fr">

    <!-- Panel Absen -->
    <div class="absen-card">
        <div id="live-clock" class="clock-display">00:00:00</div>
        <div id="live-date"  class="date-display"></div>

        <div id="location-status" class="location-status loc-loading">
            <span class="pulse-dot"></span> Mendapatkan lokasi GPS...
        </div>
        <div id="location-distance" class="text-sm text-muted mb-2"></div>

        <!-- Kamera preview -->
        <div id="cam-wrap" style="display:none;margin-bottom:1rem">
            <video id="cam-video" autoplay playsinline muted
                style="width:100%;max-width:260px;border-radius:var(--rl);border:2px solid var(--border-md);background:#000"></video>
            <canvas id="cam-canvas" style="display:none"></canvas>
            <div id="cam-preview-wrap" style="display:none;margin-top:8px">
                <img id="cam-preview" style="width:100%;max-width:260px;border-radius:var(--rl);border:2px solid var(--green-600)">
                <div class="text-xs text-muted mt-1" style="text-align:center">Foto selfie tersimpan ✓</div>
            </div>
            <div style="display:flex;gap:8px;justify-content:center;margin-top:10px">
                <button class="btn btn-sm btn-primary" id="btn-capture" onclick="capturePhoto()">📷 Ambil Foto</button>
                <button class="btn btn-sm" id="btn-retake" onclick="retakePhoto()" style="display:none">↺ Ulang</button>
            </div>
        </div>

        <!-- Hidden form -->
        <form method="POST" id="form-absen">
            <input type="hidden" name="tipe"        id="hidden-tipe">
            <input type="hidden" name="lat"         id="hidden-lat">
            <input type="hidden" name="lng"         id="hidden-lng">
            <input type="hidden" name="device"      id="hidden-device">
            <input type="hidden" name="foto_base64" id="hidden-foto">
        </form>

        <?php
        $sudahMasuk  = !empty($absenHari['jam_masuk']);
        $sudahKeluar = !empty($absenHari['jam_keluar']);
        ?>

        <?php if (!$sudahMasuk): ?>
        <button id="btn-absen-masuk" class="absen-btn-masuk absen-btn-disabled" onclick="bukakamera('masuk')">
            <div class="btn-icon-big">📷</div>
            <div style="font-weight:700">ABSEN MASUK</div>
            <div class="text-xs" style="margin-top:4px;opacity:.7">
                <?= htmlspecialchars($user['shift_nama'] ?? '—') ?><br>
                <?= $user['jam_masuk'] ? substr($user['jam_masuk'],0,5) : '' ?>
            </div>
        </button>
        <?php elseif ($sudahMasuk && !$sudahKeluar): ?>
        <div style="margin-bottom:1rem;text-align:center">
            <?php if (!empty($absenHari['foto_masuk'])): ?>
            <img src="<?= BASE_URL ?>/assets/uploads/absensi/<?= htmlspecialchars($absenHari['foto_masuk']) ?>"
                style="width:70px;height:70px;border-radius:50%;border:2px solid var(--green-600);object-fit:cover">
            <?php endif; ?>
            <div class="mt-1">
            <span class="badge badge-green" style="font-size:13px;padding:8px 16px">
                ✓ Masuk: <?= date('H:i', strtotime($absenHari['jam_masuk'])) ?>
                <?php if ($absenHari['status_masuk'] === 'terlambat'): ?>
                <span class="badge badge-amber" style="margin-left:6px">Terlambat</span>
                <?php endif; ?>
            </span>
            </div>
        </div>
        <button id="btn-absen-keluar" class="absen-btn-keluar absen-btn-disabled" onclick="bukakamera('keluar')">
            <div class="btn-icon-big">📷</div>
            <div style="font-weight:700">ABSEN KELUAR</div>
        </button>
        <?php else: ?>
        <div style="padding:1.25rem;background:rgba(34,197,94,.1);border-radius:var(--rl);border:1px solid rgba(34,197,94,.3)">
            <div style="display:flex;gap:12px;justify-content:center;margin-bottom:10px">
                <?php if (!empty($absenHari['foto_masuk'])): ?>
                <div style="text-align:center">
                    <img src="<?= BASE_URL ?>/assets/uploads/absensi/<?= htmlspecialchars($absenHari['foto_masuk']) ?>"
                        style="width:56px;height:56px;border-radius:50%;border:2px solid var(--green-600);object-fit:cover">
                    <div class="text-xs text-muted mt-1">Masuk</div>
                </div>
                <?php endif; ?>
                <?php if (!empty($absenHari['foto_keluar'])): ?>
                <div style="text-align:center">
                    <img src="<?= BASE_URL ?>/assets/uploads/absensi/<?= htmlspecialchars($absenHari['foto_keluar']) ?>"
                        style="width:56px;height:56px;border-radius:50%;border:2px solid var(--amber);object-fit:cover">
                    <div class="text-xs text-muted mt-1">Keluar</div>
                </div>
                <?php endif; ?>
            </div>
            <div style="font-size:24px;margin-bottom:8px">✅</div>
            <div style="font-weight:700;color:var(--green-400);margin-bottom:8px">Absensi Selesai</div>
            <div class="text-sm text-muted">
                Masuk:  <strong class="text-green  mono"><?= date('H:i', strtotime($absenHari['jam_masuk']))  ?></strong><br>
                Keluar: <strong class="text-amber mono"><?= date('H:i', strtotime($absenHari['jam_keluar'])) ?></strong>
            </div>
        </div>
        <?php endif; ?>

        <div style="margin-top:1rem;padding:8px 12px;background:rgba(0,0,0,.2);border-radius:var(--r);font-size:11px;color:var(--text-m)">
            Radius: <?= $radius ?>m · Toleransi: <?= $user['toleransi_terlambat'] ?? 15 ?> menit
        </div>
    </div>

    <!-- Riwayat -->
    <div>
        <div class="stat-grid mb-2" style="grid-template-columns:repeat(2,1fr)">
            <div class="stat-card green"><div class="stat-label">Hadir</div><div class="stat-value"><?=(int)$rekap['hadir']?></div></div>
            <div class="stat-card amber"><div class="stat-label">Terlambat</div><div class="stat-value"><?=(int)$rekap['terlambat']?></div></div>
        </div>
        <div class="card">
            <div class="card-header">
                <span class="card-title">Riwayat <?=bulanNama($bulan).' '.$tahun?></span>
                <form method="GET" style="display:flex;gap:6px">
                    <select name="bulan" class="sel-filter auto-submit" style="font-size:12px;padding:4px 20px 4px 8px">
                        <?php for ($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m===$bulan?'selected':''?>><?=bulanNama($m)?></option><?php endfor;?>
                    </select>
                    <select name="tahun" class="sel-filter auto-submit" style="font-size:12px;padding:4px 20px 4px 8px">
                        <?php for ($y=(int)date('Y');$y>=(int)date('Y')-2;$y--): ?><option value="<?=$y?>" <?=$y===$tahun?'selected':''?>><?=$y?></option><?php endfor;?>
                    </select>
                </form>
            </div>
            <div class="tbl-wrap" style="max-height:360px;overflow-y:auto">
                <table>
                    <thead><tr><th>Tanggal</th><th>Masuk</th><th>Keluar</th><th>Foto</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$riwayat || $riwayat->num_rows===0): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-m);padding:2rem">Tidak ada data</td></tr>
                    <?php else: while ($r = $riwayat->fetch_assoc()):
                        $bs=['tepat'=>'badge-green','terlambat'=>'badge-amber','alpha'=>'badge-red','izin'=>'badge-blue'];?>
                    <tr>
                        <td class="text-sm"><?=formatTgl($r['tanggal'])?></td>
                        <td class="mono text-sm"><?=$r['jam_masuk']?date('H:i',strtotime($r['jam_masuk'])):'—'?></td>
                        <td class="mono text-sm"><?=$r['jam_keluar']?date('H:i',strtotime($r['jam_keluar'])):'—'?></td>
                        <td>
                            <?php if (!empty($r['foto_masuk'])): ?>
                            <img src="<?= BASE_URL ?>/assets/uploads/absensi/<?= htmlspecialchars($r['foto_masuk']) ?>"
                                style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:1px solid var(--border-md)"
                                title="Foto masuk">
                            <?php else: ?><span class="text-muted text-xs">—</span><?php endif; ?>
                        </td>
                        <td><span class="badge <?=$bs[$r['status_masuk']]??'badge-gray'?>"><?=ucfirst($r['status_masuk'])?></span></td>
                    </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Hidden device info
document.getElementById('hidden-device').value = navigator.userAgent.substring(0, 200);

var camStream   = null;
var fotoData    = '';
var pendingTipe = '';

function bukakamera(tipe) {
    pendingTipe = tipe;
    fotoData    = '';
    document.getElementById('cam-wrap').style.display = '';
    document.getElementById('cam-preview-wrap').style.display = 'none';
    document.getElementById('btn-capture').style.display = '';
    document.getElementById('btn-retake').style.display = 'none';
    document.getElementById('hidden-foto').value = '';

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        // Kamera tidak tersedia — lanjutkan tanpa foto
        document.getElementById('cam-wrap').style.display = 'none';
        submitAbsen(tipe);
        return;
    }

    navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }
    }).then(function(stream) {
        camStream = stream;
        var v = document.getElementById('cam-video');
        v.srcObject = stream;
        v.play();
    }).catch(function() {
        document.getElementById('cam-wrap').style.display = 'none';
        submitAbsen(tipe);
    });
}

function capturePhoto() {
    var v = document.getElementById('cam-video');
    var c = document.getElementById('cam-canvas');
    c.width  = v.videoWidth  || 320;
    c.height = v.videoHeight || 240;
    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
    fotoData = c.toDataURL('image/jpeg', 0.75);

    // Stop camera stream
    if (camStream) { camStream.getTracks().forEach(function(t){ t.stop(); }); camStream = null; }

    // Show preview
    document.getElementById('cam-video').style.display = 'none';
    document.getElementById('cam-preview-wrap').style.display = '';
    document.getElementById('cam-preview').src = fotoData;
    document.getElementById('btn-capture').style.display = 'none';
    document.getElementById('btn-retake').style.display = '';
    document.getElementById('hidden-foto').value = fotoData;

    // Auto submit setelah foto diambil
    setTimeout(function(){ submitAbsen(pendingTipe); }, 600);
}

function retakePhoto() {
    fotoData = '';
    document.getElementById('hidden-foto').value = '';
    document.getElementById('cam-preview-wrap').style.display = 'none';
    document.getElementById('cam-video').style.display = '';
    document.getElementById('btn-capture').style.display = '';
    document.getElementById('btn-retake').style.display = 'none';
    bukakamera(pendingTipe);
}
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
