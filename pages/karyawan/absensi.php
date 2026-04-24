<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireLogin();
$user = currentUser();
if ($user['role'] !== 'karyawan') redirect(BASE_URL.'/pages/admin/absensi.php');

$uid  = (int)$user['id'];
$hari = date('Y-m-d');

// ── POST: absen masuk / keluar ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipe   = sanitize($_POST['tipe']   ?? '');
    $lat    = (float)($_POST['lat']     ?? 0);
    $lng    = (float)($_POST['lng']     ?? 0);
    $device = sanitize(substr($_POST['device'] ?? '', 0, 200));

    if (!$lat || !$lng) {
        flash('error', 'Koordinat GPS tidak valid. Aktifkan GPS dan coba lagi.');
        redirect(BASE_URL.'/pages/karyawan/absensi.php');
    }

    $offLat = (float)($user['lokasi_lat'] ?: KANTOR_LAT);
    $offLng = (float)($user['lokasi_lng'] ?: KANTOR_LNG);
    $radius = (int)($user['radius_absen'] ?: ABSEN_RADIUS);
    $jarak  = (int)round(hitungJarak($lat, $lng, $offLat, $offLng));

    if ($jarak > $radius) {
        flash('error', "Lokasi Anda {$jarak}m dari kantor. Batas absensi: {$radius}m. Anda harus berada di dalam area kantor.");
        redirect(BASE_URL.'/pages/karyawan/absensi.php');
    }

    $now      = date('Y-m-d H:i:s');
    $nowTime  = date('H:i:s');
    $absenAda = db()->query("SELECT * FROM absensi WHERE user_id=$uid AND tanggal='$hari' LIMIT 1")->fetch_assoc();
    $shiftId  = !empty($user['shift_id']) ? (int)$user['shift_id'] : null;

    if ($tipe === 'masuk') {
        if ($absenAda) {
            flash('error', 'Anda sudah absen masuk hari ini.');
            redirect(BASE_URL.'/pages/karyawan/absensi.php');
        }
        // Cek keterlambatan
        $shiftMasuk  = $user['jam_masuk'] ?? '08:00:00';
        $toleransi   = (int)($user['toleransi_terlambat'] ?? 15);
        $batasMasuk  = date('H:i:s', strtotime($shiftMasuk) + $toleransi * 60);
        $statusMasuk = ($nowTime > $batasMasuk) ? 'terlambat' : 'tepat';

        // Use direct query — avoids bind_param null shift_id issue
        $shiftSql = $shiftId ? $shiftId : 'NULL';
        $lat_e = esc($lat); $lng_e = esc($lng);
        $now_e = esc($now); $dev_e = esc($device);
        $stm_e = esc($statusMasuk);
        db()->query("INSERT INTO absensi
            (user_id,tanggal,shift_id,jam_masuk,lat_masuk,lng_masuk,jarak_masuk,status_masuk,status_kehadiran,device_info)
            VALUES ($uid,'$hari',$shiftSql,'$now_e',$lat_e,$lng_e,$jarak,'$stm_e','hadir','$dev_e')");

        if (db()->error) {
            flash('error', 'Gagal menyimpan absensi: '.db()->error);
        } else {
            $msg = $statusMasuk === 'terlambat'
                ? "Absen masuk berhasil — Anda TERLAMBAT (jarak: {$jarak}m)"
                : "Absen masuk berhasil — Tepat waktu (jarak: {$jarak}m)";
            flash($statusMasuk === 'terlambat' ? 'amber' : 'success', $msg);
        }

    } elseif ($tipe === 'keluar') {
        if (!$absenAda) {
            flash('error', 'Anda belum absen masuk hari ini.');
            redirect(BASE_URL.'/pages/karyawan/absensi.php');
        }
        if ($absenAda['jam_keluar']) {
            flash('error', 'Anda sudah absen keluar hari ini.');
            redirect(BASE_URL.'/pages/karyawan/absensi.php');
        }
        $lat_e = esc($lat); $lng_e = esc($lng);
        $now_e = esc($now);
        db()->query("UPDATE absensi SET jam_keluar='$now_e',lat_keluar=$lat_e,lng_keluar=$lng_e,jarak_keluar=$jarak WHERE user_id=$uid AND tanggal='$hari'");
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

$pageTitle  = 'Absensi GPS';
$pageSub    = date('l, d F Y');
$activePage = 'absensi';
include __DIR__.'/../../includes/header.php';
?>

<!-- Config for JS -->
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

        <!-- Hidden form -->
        <form method="POST" id="form-absen">
            <input type="hidden" name="tipe"   id="hidden-tipe">
            <input type="hidden" name="lat"    id="hidden-lat">
            <input type="hidden" name="lng"    id="hidden-lng">
            <input type="hidden" name="device" id="hidden-device">
        </form>

        <?php
        $sudahMasuk  = !empty($absenHari['jam_masuk']);
        $sudahKeluar = !empty($absenHari['jam_keluar']);
        ?>

        <?php if (!$sudahMasuk): ?>
        <button id="btn-absen-masuk" class="absen-btn-masuk absen-btn-disabled" onclick="submitAbsen('masuk')">
            <div class="btn-icon-big">▶</div>
            <div style="font-weight:700">ABSEN MASUK</div>
            <div class="text-xs" style="margin-top:4px;opacity:.7">
                <?= htmlspecialchars($user['shift_nama'] ?? '—') ?><br>
                <?= $user['jam_masuk'] ? substr($user['jam_masuk'],0,5) : '' ?>
            </div>
        </button>
        <?php elseif ($sudahMasuk && !$sudahKeluar): ?>
        <div style="margin-bottom:1rem">
            <span class="badge badge-green" style="font-size:13px;padding:8px 16px">
                ✓ Masuk: <?= date('H:i', strtotime($absenHari['jam_masuk'])) ?>
                <?php if ($absenHari['status_masuk'] === 'terlambat'): ?>
                <span class="badge badge-amber" style="margin-left:6px">Terlambat</span>
                <?php endif; ?>
            </span>
        </div>
        <button id="btn-absen-keluar" class="absen-btn-keluar absen-btn-disabled" onclick="submitAbsen('keluar')">
            <div class="btn-icon-big">⏹</div>
            <div style="font-weight:700">ABSEN KELUAR</div>
        </button>
        <?php else: ?>
        <div style="padding:1.25rem;background:rgba(34,197,94,.1);border-radius:var(--rl);border:1px solid rgba(34,197,94,.3)">
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
            <div class="tbl-wrap" style="max-height:340px;overflow-y:auto">
                <table>
                    <thead><tr><th>Tanggal</th><th>Masuk</th><th>Keluar</th><th>Jarak</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$riwayat || $riwayat->num_rows===0): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-m);padding:2rem">Tidak ada data</td></tr>
                    <?php else: while ($r = $riwayat->fetch_assoc()):
                        $bs=['tepat'=>'badge-green','terlambat'=>'badge-amber','alpha'=>'badge-red','izin'=>'badge-blue'];?>
                    <tr>
                        <td class="text-sm"><?=formatTgl($r['tanggal'])?></td>
                        <td class="mono text-sm"><?=$r['jam_masuk']?date('H:i',strtotime($r['jam_masuk'])):'—'?></td>
                        <td class="mono text-sm"><?=$r['jam_keluar']?date('H:i',strtotime($r['jam_keluar'])):'—'?></td>
                        <td class="text-sm text-muted"><?=$r['jarak_masuk']?$r['jarak_masuk'].'m':'—'?></td>
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
document.getElementById('hidden-device').value = navigator.userAgent.substring(0, 200);
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
