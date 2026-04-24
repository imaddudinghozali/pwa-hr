-- ============================================================
-- SIMK PWA - PT Pesta Hijau Abadi
-- Database Schema Lengkap
-- ============================================================

CREATE DATABASE IF NOT EXISTS simk_pha CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simk_pha;

-- ============================================================
-- TABEL MASTER
-- ============================================================

CREATE TABLE departemen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    kode VARCHAR(20) UNIQUE NOT NULL,
    lokasi_lat DECIMAL(10,8),
    lokasi_lng DECIMAL(11,8),
    radius_absen INT DEFAULT 100 COMMENT 'meter',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE jabatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    departemen_id INT,
    gaji_pokok DECIMAL(15,2) DEFAULT 0,
    tunjangan_jabatan DECIMAL(15,2) DEFAULT 0,
    FOREIGN KEY (departemen_id) REFERENCES departemen(id) ON DELETE SET NULL
);

CREATE TABLE shift (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(50) NOT NULL,
    jam_masuk TIME NOT NULL,
    jam_keluar TIME NOT NULL,
    toleransi_terlambat INT DEFAULT 15 COMMENT 'menit',
    warna VARCHAR(7) DEFAULT '#22c55e' COMMENT 'hex color'
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nip VARCHAR(30) UNIQUE NOT NULL,
    nama VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','hrd','karyawan') DEFAULT 'karyawan',
    departemen_id INT,
    jabatan_id INT,
    shift_id INT,
    tanggal_bergabung DATE,
    tanggal_lahir DATE,
    jenis_kelamin ENUM('L','P'),
    status ENUM('aktif','nonaktif','cuti') DEFAULT 'aktif',
    foto VARCHAR(255),
    alamat TEXT,
    telepon VARCHAR(20),
    no_rekening VARCHAR(30),
    nama_bank VARCHAR(50),
    sisa_cuti INT DEFAULT 12,
    fcm_token VARCHAR(255) COMMENT 'Push notification token',
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (departemen_id) REFERENCES departemen(id) ON DELETE SET NULL,
    FOREIGN KEY (jabatan_id) REFERENCES jabatan(id) ON DELETE SET NULL,
    FOREIGN KEY (shift_id) REFERENCES shift(id) ON DELETE SET NULL
);

-- ============================================================
-- ABSENSI
-- ============================================================

CREATE TABLE absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tanggal DATE NOT NULL,
    shift_id INT,
    jam_masuk DATETIME,
    jam_keluar DATETIME,
    lat_masuk DECIMAL(10,8),
    lng_masuk DECIMAL(11,8),
    lat_keluar DECIMAL(10,8),
    lng_keluar DECIMAL(11,8),
    jarak_masuk INT COMMENT 'meter dari kantor',
    jarak_keluar INT COMMENT 'meter dari kantor',
    status_masuk ENUM('tepat','terlambat','izin','alpha') DEFAULT 'tepat',
    status_kehadiran ENUM('hadir','izin','sakit','cuti','alpha','libur') DEFAULT 'hadir',
    foto_masuk VARCHAR(255),
    foto_keluar VARCHAR(255),
    keterangan TEXT,
    device_info VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_absen (user_id, tanggal),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shift(id) ON DELETE SET NULL
);

-- ============================================================
-- LEMBUR
-- ============================================================

CREATE TABLE lembur (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    durasi_menit INT,
    alasan TEXT,
    status ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
    disetujui_oleh INT,
    catatan_approver TEXT,
    upah_lembur DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (disetujui_oleh) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- CUTI
-- ============================================================

CREATE TABLE jenis_cuti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    max_hari INT DEFAULT 1,
    perlu_dokumen TINYINT(1) DEFAULT 0,
    keterangan TEXT
);

CREATE TABLE pengajuan_cuti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    jenis_cuti_id INT NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    jumlah_hari INT,
    alasan TEXT NOT NULL,
    dokumen VARCHAR(255),
    status ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
    disetujui_oleh INT,
    catatan_approver TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (jenis_cuti_id) REFERENCES jenis_cuti(id),
    FOREIGN KEY (disetujui_oleh) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- PENGGAJIAN
-- ============================================================

CREATE TABLE komponen_gaji (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    tipe ENUM('tunjangan','potongan') NOT NULL,
    nilai DECIMAL(15,2) DEFAULT 0,
    is_persen TINYINT(1) DEFAULT 0 COMMENT '1=persen dari gaji pokok',
    keterangan VARCHAR(255)
);

CREATE TABLE slip_gaji (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bulan INT NOT NULL,
    tahun INT NOT NULL,
    gaji_pokok DECIMAL(15,2) DEFAULT 0,
    tunjangan_jabatan DECIMAL(15,2) DEFAULT 0,
    tunjangan_makan DECIMAL(15,2) DEFAULT 0,
    tunjangan_transport DECIMAL(15,2) DEFAULT 0,
    upah_lembur DECIMAL(15,2) DEFAULT 0,
    potongan_absen DECIMAL(15,2) DEFAULT 0,
    potongan_bpjs_tk DECIMAL(15,2) DEFAULT 0,
    potongan_bpjs_kes DECIMAL(15,2) DEFAULT 0,
    potongan_pph21 DECIMAL(15,2) DEFAULT 0,
    potongan_lain DECIMAL(15,2) DEFAULT 0,
    gaji_bersih DECIMAL(15,2) DEFAULT 0,
    hari_kerja INT DEFAULT 0,
    hari_hadir INT DEFAULT 0,
    hari_alpha INT DEFAULT 0,
    total_lembur_jam DECIMAL(5,2) DEFAULT 0,
    status ENUM('draft','final','dibayar') DEFAULT 'draft',
    tanggal_bayar DATE,
    dibuat_oleh INT,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_slip (user_id, bulan, tahun),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- NOTIFIKASI
-- ============================================================

CREATE TABLE notifikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    judul VARCHAR(200) NOT NULL,
    pesan TEXT NOT NULL,
    tipe ENUM('info','sukses','peringatan','bahaya') DEFAULT 'info',
    sudah_dibaca TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- DATA AWAL
-- ============================================================

INSERT INTO departemen (nama, kode, lokasi_lat, lokasi_lng, radius_absen) VALUES
('Produksi', 'PRD', -6.2088, 106.8456, 150),
('Teknologi Informasi', 'TI', -6.2088, 106.8456, 200),
('Sumber Daya Manusia', 'SDM', -6.2088, 106.8456, 200),
('Keuangan & Akuntansi', 'KEU', -6.2088, 106.8456, 200),
('Pemasaran', 'MKT', -6.2088, 106.8456, 200),
('Operasional', 'OPS', -6.2088, 106.8456, 200);

INSERT INTO shift (nama, jam_masuk, jam_keluar, toleransi_terlambat, warna) VALUES
('Shift Pagi', '07:00:00', '15:00:00', 15, '#22c55e'),
('Shift Siang', '15:00:00', '23:00:00', 15, '#f59e0b'),
('Shift Malam', '23:00:00', '07:00:00', 15, '#6366f1'),
('Normal Office', '08:00:00', '17:00:00', 15, '#0ea5e9');

INSERT INTO jabatan (nama, departemen_id, gaji_pokok, tunjangan_jabatan) VALUES
('Manager Produksi', 1, 18000000, 2500000),
('Supervisor Produksi', 1, 13000000, 1500000),
('Operator Mesin', 1, 7500000, 500000),
('IT Manager', 2, 18000000, 2500000),
('Senior Developer', 2, 14000000, 1500000),
('Junior Developer', 2, 9000000, 750000),
('HRD Manager', 3, 17000000, 2000000),
('Staff HRD', 3, 8500000, 750000),
('Finance Manager', 4, 18000000, 2500000),
('Accounting Staff', 4, 9000000, 750000),
('Marketing Manager', 5, 17000000, 2000000),
('Digital Marketing', 5, 9500000, 750000),
('Ops Manager', 6, 16000000, 2000000),
('Logistik Staff', 6, 8000000, 500000);

INSERT INTO jenis_cuti (nama, max_hari, perlu_dokumen) VALUES
('Cuti Tahunan', 12, 0),
('Cuti Sakit', 90, 1),
('Cuti Melahirkan', 90, 1),
('Cuti Menikah', 3, 1),
('Cuti Duka', 3, 1),
('Cuti Bersama', 1, 0);

INSERT INTO komponen_gaji (nama, tipe, nilai, is_persen) VALUES
('Tunjangan Makan', 'tunjangan', 750000, 0),
('Tunjangan Transport', 'tunjangan', 500000, 0),
('Tunjangan Kehadiran', 'tunjangan', 500000, 0),
('BPJS Ketenagakerjaan', 'potongan', 2, 1),
('BPJS Kesehatan', 'potongan', 1, 1),
('PPh21', 'potongan', 5, 1);

-- ============================================================
-- AKUN DEFAULT
-- Password di-set melalui setup.php (password_hash PHP)
-- Placeholder 'SETUP_REQUIRED' akan diganti oleh setup.php
-- ============================================================
INSERT INTO users (nip, nama, email, password, role, departemen_id, jabatan_id, shift_id, tanggal_bergabung, jenis_kelamin, status) VALUES
('ADM001', 'Administrator',  'admin@pestahijau.co.id',     'SETUP_REQUIRED', 'admin',    3, 7, 4, '2020-01-01', 'L', 'aktif'),
('HRD001', 'Sari Dewi',      'sari.dewi@pestahijau.co.id', 'SETUP_REQUIRED', 'hrd',      3, 8, 4, '2020-03-01', 'P', 'aktif'),
('EMP001', 'Andi Kurniawan', 'andi.k@pestahijau.co.id',    'SETUP_REQUIRED', 'karyawan', 2, 5, 4, '2021-06-01', 'L', 'aktif'),
('EMP002', 'Budi Santoso',   'budi.s@pestahijau.co.id',    'SETUP_REQUIRED', 'karyawan', 1, 3, 1, '2021-08-15', 'L', 'aktif'),
('EMP003', 'Rina Marlina',   'rina.m@pestahijau.co.id',    'SETUP_REQUIRED', 'karyawan', 5, 12, 4, '2022-01-10', 'P', 'aktif');

-- ============================================================
-- SETELAH IMPORT: buka http://localhost/pwa-hr/setup.php
-- untuk mengeset password semua akun di atas.
-- ============================================================
