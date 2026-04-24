# SIMK PWA — PT Pesta Hijau Abadi
## Sistem Informasi Manajemen Karyawan Berbasis Progressive Web App

---

## Fitur Lengkap

### 🟢 Absensi GPS
- Validasi lokasi real-time menggunakan HTML5 Geolocation API
- Radius absensi per departemen (configurable)
- Deteksi keterlambatan otomatis berdasarkan shift
- Foto & device info tersimpan saat absen
- Status: Tepat, Terlambat, Izin, Alpha

### ⏱ Lembur
- Pengajuan lembur oleh karyawan
- Approval 2 level (HRD/Admin)
- Perhitungan upah otomatis: Gaji ÷ 173 × 1.5 × Jam (UU Ketenagakerjaan)
- Notifikasi real-time ke HRD

### 🏖 Pengajuan Cuti
- 6 jenis cuti: Tahunan, Sakit, Melahirkan, Menikah, Duka, Bersama
- Validasi kuota cuti tahunan otomatis
- Deteksi tumpang tindih tanggal
- Approval oleh HRD dengan catatan
- Pengurangan saldo cuti otomatis

### 💰 Penggajian
- Generate slip gaji otomatis dari data absensi + lembur
- Komponen: gaji pokok, tunjangan (jabatan/makan/transport), upah lembur
- Potongan: BPJS TK (2%), BPJS Kes (1%), PPh21 (5%), ketidakhadiran
- Status: Draft → Final → Dibayar
- Cetak slip gaji (print-friendly)

### 📱 PWA Features
- Installable (Add to Home Screen)
- Service Worker + offline cache
- Push notification siap (FCM-ready)
- Responsive untuk mobile & desktop

---

## Struktur Proyek

```
pwa-hr/
├── config/
│   └── database.php          # DB config + helper functions
├── includes/
│   ├── header.php            # Layout header + sidebar
│   └── footer.php            # Layout footer + PWA banner
├── assets/
│   ├── css/app.css           # Stylesheet (Dark Forest theme)
│   ├── js/app.js             # PWA + GPS + UI logic
│   └── icons/                # PWA icons (72–512px)
├── pages/
│   ├── admin/
│   │   ├── dashboard.php     # Dashboard admin
│   │   ├── karyawan.php      # CRUD karyawan
│   │   ├── karyawan_detail.php
│   │   ├── absensi.php       # Rekap absensi
│   │   ├── lembur.php        # Approval lembur
│   │   ├── cuti.php          # Approval cuti
│   │   ├── penggajian.php    # Generate + manage slip gaji
│   │   ├── slip_detail.php   # Detail slip gaji
│   │   ├── departemen.php    # Master departemen + GPS
│   │   └── shift.php         # Master shift kerja
│   └── karyawan/
│       ├── dashboard.php     # Beranda karyawan
│       ├── absensi.php       # Absensi GPS (CORE)
│       ├── lembur.php        # Pengajuan lembur
│       ├── cuti.php          # Pengajuan cuti
│       ├── slip_gaji.php     # Lihat slip gaji
│       ├── profil.php        # Edit profil + ganti password
│       └── notifikasi.php    # Inbox notifikasi
├── index.php                 # Auto-redirect
├── login.php                 # Halaman login
├── logout.php                # Logout
├── sw.js                     # Service Worker
├── manifest.json             # PWA Manifest
├── offline.html              # Halaman offline
└── database.sql              # Schema + data awal
```

---

## Cara Setup

### 1. Persyaratan
- PHP >= 7.4 (dengan ekstensi: mysqli, gd)
- MySQL >= 5.7 / MariaDB >= 10.3
- XAMPP / Laragon / WAMP
- HTTPS (wajib untuk GPS API di production) — di localhost bisa HTTP

### 2. Import Database
```bash
mysql -u root -p < database.sql
```
Atau via **phpMyAdmin**: Import → pilih `database.sql`

### 3. Konfigurasi
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'simk_pha');
define('BASE_URL', 'http://localhost/pwa-hr');

// Koordinat kantor utama (fallback)
define('KANTOR_LAT', -6.2088);
define('KANTOR_LNG', 106.8456);
define('ABSEN_RADIUS', 200); // meter
```

### 4. Letakkan di Web Server
- **XAMPP**: `C:/xampp/htdocs/pwa-hr/`
- **Laragon**: `C:/laragon/www/pwa-hr/`

### 5. Akses
```
http://localhost/pwa-hr/
```

---

## Akun Default

| Role      | NIP/Email           | Password    |
|-----------|---------------------|-------------|
| Admin     | `ADM001`            | `Admin@123` |
| HRD       | `HRD001`            | `Admin@123` |
| Karyawan  | `EMP001`            | `Admin@123` |

> ⚠️ Ganti password default setelah setup!

---

## Konfigurasi GPS Absensi

1. Login sebagai Admin
2. Buka **Master Data → Departemen**
3. Edit departemen, masukkan koordinat GPS kantor:
   - Buka Google Maps → klik kanan lokasi kantor → salin koordinat
   - Set **Radius Absensi** (default: 200 meter)
4. Karyawan akan divalidasi lokasi berdasarkan departemen masing-masing

### Testing GPS di Localhost
Geolocation API di browser memerlukan **HTTPS** atau **localhost**.
- Di localhost sudah bisa langsung digunakan
- Di server production, wajib setup SSL/HTTPS

---

## PWA Install

Setelah membuka di browser mobile (Chrome/Edge):
1. Muncul banner "Install SIMK PHA" di bagian bawah
2. Atau klik menu browser → "Add to Home Screen"
3. Aplikasi dapat diakses seperti native app

---

## Teknologi

| Layer | Teknologi |
|-------|-----------|
| Backend | PHP 8.0+ Native (MySQLi Prepared Statements) |
| Database | MySQL 5.7+ |
| Frontend | HTML5, CSS3, Vanilla JS |
| PWA | Service Worker, Web App Manifest, Cache API |
| GPS | HTML5 Geolocation API (Haversine formula) |
| Font | Plus Jakarta Sans + JetBrains Mono (Google Fonts) |

**Tidak menggunakan framework apapun** — murni PHP native.

---

## Keamanan
- ✅ Prepared statements (anti SQL injection)
- ✅ `password_hash()` / `password_verify()` (bcrypt cost 12)
- ✅ `htmlspecialchars()` pada semua output (anti XSS)
- ✅ Session regeneration saat login
- ✅ Role-based access control (admin/hrd/karyawan)
- ✅ Validasi GPS server-side (tidak hanya client-side)
"# pwa-hr" 
