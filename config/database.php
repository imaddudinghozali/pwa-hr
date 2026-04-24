<?php
// ============================================================
// config/database.php  —  SIMK PHA
// ============================================================

define('DB_HOST',      'localhost');
define('DB_USER',      'root');
define('DB_PASS',      '');
define('DB_NAME',      'simk_pha');
define('BASE_URL',     'http://localhost/pwa-hr');   // ← Sesuaikan nama folder
define('APP_NAME',     'SIMK PHA');
define('COMPANY_NAME', 'PT Pesta Hijau Abadi');

// Koordinat kantor utama (fallback jika departemen belum diatur)
define('KANTOR_LAT',   -6.2088);
define('KANTOR_LNG',   106.8456);
define('ABSEN_RADIUS', 200);   // meter

// ── Database ──────────────────────────────────────────────────
function db(): mysqli {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="font-family:sans-serif;padding:2rem;color:#dc2626">'
               .'<h2>Koneksi Database Gagal</h2>'
               .'<p>'.htmlspecialchars($conn->connect_error).'</p>'
               .'<p>Periksa setting di <code>config/database.php</code></p></div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function dbPrepare(string $sql): mysqli_stmt {
    $stmt = db()->prepare($sql);
    if (!$stmt) {
        die('<pre>SQL Prepare Error: '.htmlspecialchars(db()->error)."\nSQL: ".htmlspecialchars($sql).'</pre>');
    }
    return $stmt;
}

// ── Sanitize / Escape ─────────────────────────────────────────
function sanitize(mixed $v): string {
    return htmlspecialchars(strip_tags(trim((string)$v)));
}
function esc(mixed $v): string {
    return db()->real_escape_string((string)$v);
}

// ── HTTP ──────────────────────────────────────────────────────
function redirect(string $url): void { header("Location: $url"); exit; }

// ── Flash Messages ────────────────────────────────────────────
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ── Formatting ────────────────────────────────────────────────
function formatRp(float $n): string {
    return 'Rp '.number_format($n, 0, ',', '.');
}
function formatTgl(string $d = ''): string {
    if (!$d || $d === '0000-00-00') return '—';
    $ts = strtotime($d);
    if ($ts === false) return '—';
    $bln = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return date('d',$ts).' '.$bln[(int)date('n',$ts)].' '.date('Y',$ts);
}
function bulanNama(int $n): string {
    return ['','Januari','Februari','Maret','April','Mei','Juni',
            'Juli','Agustus','September','Oktober','November','Desember'][$n] ?? '';
}

// ── Avatar ────────────────────────────────────────────────────
function initials(string $nama): string {
    $p = array_values(array_filter(explode(' ', trim($nama))));
    if (empty($p)) return '?';
    return strtoupper(substr($p[0],0,1).(isset($p[1]) ? substr($p[1],0,1) : ''));
}
function avatarBg(int $id): string {
    $c = ['#16a34a','#0284c7','#7c3aed','#dc2626','#d97706','#0891b2','#059669','#9333ea'];
    return $c[$id % count($c)];
}

// ── GPS ───────────────────────────────────────────────────────
function hitungJarak(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R    = 6371000;
    $dLat = deg2rad($lat2-$lat1);
    $dLng = deg2rad($lng2-$lng1);
    $a    = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}

// ── Auth ──────────────────────────────────────────────────────
function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    if (!empty($_SESSION['_user_cache'])) return $_SESSION['_user_cache'];
    $id = (int)$_SESSION['user_id'];
    $r  = db()->query(
        "SELECT u.*, d.nama AS dept_nama, d.lokasi_lat, d.lokasi_lng, d.radius_absen,
                j.nama AS jabatan_nama, j.gaji_pokok, j.tunjangan_jabatan,
                s.nama AS shift_nama, s.jam_masuk, s.jam_keluar, s.toleransi_terlambat
         FROM   users u
         LEFT JOIN departemen d ON u.departemen_id=d.id
         LEFT JOIN jabatan    j ON u.jabatan_id=j.id
         LEFT JOIN shift      s ON u.shift_id=s.id
         WHERE  u.id=$id LIMIT 1"
    );
    $user = ($r && $r->num_rows) ? $r->fetch_assoc() : null;
    if ($user) $_SESSION['_user_cache'] = $user;
    return $user;
}
function clearUserCache(): void { unset($_SESSION['_user_cache']); }

function requireLogin(): void {
    if (!isLoggedIn()) redirect(BASE_URL.'/login.php');
}
function requireAdmin(): void {
    requireLogin();
    $u = currentUser();
    if (!$u || !in_array($u['role'], ['admin','hrd'], true))
        redirect(BASE_URL.'/pages/karyawan/dashboard.php');
}

// ── Master data helpers ───────────────────────────────────────
function getDepartemen(): array {
    $r = db()->query("SELECT * FROM departemen ORDER BY nama");
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
function getJabatan(?int $deptId = null): array {
    $sql = "SELECT j.*, d.nama AS nama_dept FROM jabatan j LEFT JOIN departemen d ON j.departemen_id=d.id";
    if ($deptId) $sql .= " WHERE j.departemen_id=".(int)$deptId;
    $r = db()->query($sql." ORDER BY j.nama");
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
