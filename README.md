# PWA-HR V1

PWA-HR adalah aplikasi manajemen HR berbasis PHP native dan MySQL untuk kebutuhan operasional karyawan. Aplikasi ini dibuat sebagai Progressive Web App (PWA), sehingga dapat dibuka lewat browser desktop/mobile dan dapat di-install ke home screen perangkat.

Project ini menggunakan nama aplikasi **SIMK PHA** untuk PT Pesta Hijau Abadi.

## Fitur Utama

- Dashboard admin dan karyawan.
- Login berbasis role: admin, HRD, dan karyawan.
- Manajemen data karyawan, departemen, jabatan, dan shift kerja.
- Absensi kamera dengan validasi GPS radius kantor/departemen.
- Upload foto absensi dari kamera, disimpan sebagai JPEG.
- Rekap absensi dan riwayat absensi bulanan.
- Pengajuan dan approval lembur.
- Pengajuan dan approval cuti.
- Pengajuan dan approval reimburse.
- Chat internal antara HR/Admin dan karyawan.
- Penggajian manual oleh HRD/Admin.
- Slip gaji karyawan.
- Notifikasi karyawan.
- PWA manifest, service worker, offline page, dan install prompt.
- Tampilan responsive untuk desktop dan mobile.

## Role User

### Admin

- Mengakses dashboard admin.
- Mengelola data karyawan.
- Mengelola master departemen dan shift.
- Melihat rekap absensi.
- Menyetujui/menolak cuti, lembur, dan reimburse.
- Mengelola slip gaji.
- Mengakses chat.

### HRD

- Secara umum diperlakukan seperti admin pada halaman operasional HR.
- Dapat mengelola penggajian, approval, data karyawan, dan komunikasi HR.

### Karyawan

- Mengakses dashboard karyawan.
- Melakukan absensi kamera.
- Mengajukan cuti, lembur, dan reimburse.
- Melihat slip gaji sendiri.
- Mengedit profil pribadi.
- Mengakses notifikasi dan chat HR.

## Konsep Penggajian V1

Penggajian pada V1 bersifat **manual dikontrol HRD/Admin**.

HRD/Admin menginput:

- Gaji pokok
- Tunjangan jabatan
- Tunjangan makan
- Tunjangan transport
- Bonus
- Upah lembur manual
- Potongan absen
- Potongan BPJS
- Potongan PPh21
- Potongan lain-lain
- Catatan

Sistem hanya menjumlahkan total gaji dari input tersebut. Data absensi, lembur, dan kontrak tidak otomatis menentukan nominal gaji, kecuali sebagai referensi tampilan bila diperlukan.

## Cara Install di XAMPP

### 1. Persyaratan

- XAMPP dengan Apache dan MySQL/MariaDB aktif.
- PHP 8.x direkomendasikan.
- Extension PHP yang dibutuhkan:
  - `mysqli`
  - `gd`
  - `fileinfo` atau dukungan MIME gambar setara
- Browser modern seperti Chrome, Edge, atau Firefox.

### 2. Letakkan Project

Letakkan folder project di:

```text
C:\xampp\htdocs\pwa-hr
```

URL lokal:

```text
http://localhost/pwa-hr/
```

### 3. Setup Database

Database default:

```text
simk_pha
```

Import melalui phpMyAdmin:

1. Buka `http://localhost/phpmyadmin`.
2. Pilih menu **Import**.
3. Pilih file [database.sql](database.sql).
4. Jalankan import.

Atau lewat terminal:

```bash
mysql -u root -p < database.sql
```

Jika project sudah pernah di-import dan ada perubahan tabel tambahan, jalankan juga:

```text
alter_tables.sql
```

### 4. Konfigurasi Database

File konfigurasi ada di [config/database.php](config/database.php).

Default konfigurasi lokal:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'simk_pha');
```

Sesuaikan jika username, password, atau nama database berbeda.

### 5. Inisialisasi Password Akun Demo

Setelah import database, buka:

```text
http://localhost/pwa-hr/setup.php
```

Halaman ini mengisi password untuk akun demo yang masih berstatus `SETUP_REQUIRED`.

Default password pada form setup:

```text
Admin@123
```

## Akun Demo

Setelah menjalankan `setup.php`, akun demo yang tersedia:

| Role | NIP | Email | Password Default |
| --- | --- | --- | --- |
| Admin | `ADM001` | `admin@pestahijau.co.id` | `Admin@123` |
| HRD | `HRD001` | `sari.dewi@pestahijau.co.id` | `Admin@123` |
| Karyawan | `EMP001` | `andi.k@pestahijau.co.id` | `Admin@123` |
| Karyawan | `EMP002` | `budi.s@pestahijau.co.id` | `Admin@123` |
| Karyawan | `EMP003` | `rina.m@pestahijau.co.id` | `Admin@123` |

Login dapat memakai NIP atau email.

## Cara Menjalankan

1. Nyalakan Apache dan MySQL dari XAMPP Control Panel.
2. Pastikan database sudah di-import.
3. Pastikan `setup.php` sudah dijalankan untuk mengatur password demo.
4. Buka:

```text
http://localhost/pwa-hr/
```

5. Login menggunakan akun demo.

## Catatan Absensi Kamera

- Absensi karyawan membutuhkan GPS dan kamera.
- Kamera harus berhasil mengambil foto sebelum form absensi dikirim.
- Foto absensi wajib berupa JPEG base64 valid.
- Server menolak foto kosong, base64 rusak, non-JPEG, ukuran decoded lebih dari 2 MB, dan folder upload yang tidak writable.
- Folder upload absensi:

```text
assets/uploads/absensi
```

- Di localhost, kamera dan GPS umumnya dapat berjalan lewat `http://localhost`.
- Di hosting/production, gunakan HTTPS agar `getUserMedia()` dan Geolocation API berjalan stabil.

## Struktur Folder

```text
pwa-hr/
|-- api/
|   `-- chat.php
|-- assets/
|   |-- css/
|   |   `-- app.css
|   |-- icons/
|   |-- js/
|   |   `-- app.js
|   `-- uploads/
|       `-- absensi/
|-- config/
|   `-- database.php
|-- includes/
|   |-- header.php
|   `-- footer.php
|-- pages/
|   |-- admin/
|   |   |-- absensi.php
|   |   |-- chat.php
|   |   |-- cuti.php
|   |   |-- dashboard.php
|   |   |-- departemen.php
|   |   |-- export_karyawan.php
|   |   |-- karyawan.php
|   |   |-- karyawan_detail.php
|   |   |-- lembur.php
|   |   |-- penggajian.php
|   |   |-- reimburse.php
|   |   |-- shift.php
|   |   `-- slip_detail.php
|   `-- karyawan/
|       |-- absensi.php
|       |-- chat.php
|       |-- cuti.php
|       |-- dashboard.php
|       |-- lembur.php
|       |-- notifikasi.php
|       |-- profil.php
|       |-- reimburse.php
|       `-- slip_gaji.php
|-- alter_tables.sql
|-- database.sql
|-- index.php
|-- login.php
|-- logout.php
|-- manifest.json
|-- offline.html
|-- setup.php
`-- sw.js
```

## File Penting

- [database.sql](database.sql): schema awal dan data demo.
- [alter_tables.sql](alter_tables.sql): perubahan tabel tambahan.
- [config/database.php](config/database.php): koneksi database, helper auth, helper format, dan konfigurasi GPS fallback.
- [assets/css/app.css](assets/css/app.css): styling global aplikasi.
- [assets/js/app.js](assets/js/app.js): PWA, helper UI, GPS, dan guard submit absensi.
- [pages/karyawan/absensi.php](pages/karyawan/absensi.php): absensi kamera karyawan.
- [pages/admin/penggajian.php](pages/admin/penggajian.php): manajemen slip gaji manual.
- [api/chat.php](api/chat.php): API chat internal.

## Catatan Keamanan

- Ganti password default setelah setup.
- Hapus atau batasi akses [setup.php](setup.php) setelah aplikasi siap digunakan.
- Gunakan HTTPS di production, terutama untuk kamera, GPS, session cookie, dan PWA.
- Pastikan folder upload hanya menerima tipe file yang divalidasi aplikasi.
- Pastikan folder `assets/uploads` tidak mengizinkan eksekusi PHP.
- Batasi permission file dan folder sesuai kebutuhan server.
- Backup database secara berkala.
- Jangan commit data rahasia, credential production, atau file upload sensitif.
- Validasi role sudah diterapkan pada halaman admin/karyawan, tetapi tetap lakukan audit sebelum production.
- Tambahkan CSRF protection untuk deployment production jika fitur write-action dibuka ke jaringan publik.

## Status V1

PWA-HR V1 siap digunakan untuk demo dan pengujian internal di XAMPP. Untuk production, lakukan hardening tambahan pada HTTPS, session cookie, CSRF, backup, permission upload, dan monitoring error server.
