-- ============================================================
-- PWA-HR Railway Migration / Repair Script
-- Jalankan di database Railway yang dipakai service pwa-hr.
-- Script ini aman dijalankan berulang untuk melengkapi tabel/kolom
-- yang sering belum sama dengan schema lokal.
-- ============================================================

SET @db := DATABASE();

-- ============================================================
-- 1. Master columns
-- ============================================================

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='departemen' AND COLUMN_NAME='kepala');
SET @sql := IF(@exists=0, 'ALTER TABLE departemen ADD COLUMN kepala VARCHAR(100) NULL AFTER radius_absen', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='jenis_karyawan');
SET @sql := IF(@exists=0, 'ALTER TABLE users ADD COLUMN jenis_karyawan ENUM(''tetap'',''kontrak'',''magang'') DEFAULT ''tetap'' AFTER status', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='tanggal_kontrak_selesai');
SET @sql := IF(@exists=0, 'ALTER TABLE users ADD COLUMN tanggal_kontrak_selesai DATE NULL AFTER tanggal_bergabung', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='gaji_pokok_override');
SET @sql := IF(@exists=0, 'ALTER TABLE users ADD COLUMN gaji_pokok_override DECIMAL(15,2) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='tunjangan_jabatan_override');
SET @sql := IF(@exists=0, 'ALTER TABLE users ADD COLUMN tunjangan_jabatan_override DECIMAL(15,2) DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================
-- 2. Cuti
-- ============================================================

CREATE TABLE IF NOT EXISTS jenis_cuti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    max_hari INT DEFAULT 1,
    perlu_dokumen TINYINT(1) DEFAULT 0,
    keterangan TEXT
);

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jenis_cuti' AND COLUMN_NAME='max_hari');
SET @sql := IF(@exists=0, 'ALTER TABLE jenis_cuti ADD COLUMN max_hari INT DEFAULT 1 AFTER nama', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jenis_cuti' AND COLUMN_NAME='perlu_dokumen');
SET @sql := IF(@exists=0, 'ALTER TABLE jenis_cuti ADD COLUMN perlu_dokumen TINYINT(1) DEFAULT 0 AFTER max_hari', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jenis_cuti' AND COLUMN_NAME='keterangan');
SET @sql := IF(@exists=0, 'ALTER TABLE jenis_cuti ADD COLUMN keterangan TEXT AFTER perlu_dokumen', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT IGNORE INTO jenis_cuti (id, nama, max_hari, perlu_dokumen) VALUES
(1, 'Cuti Tahunan', 12, 0),
(2, 'Cuti Sakit', 90, 1),
(3, 'Cuti Melahirkan', 90, 1),
(4, 'Cuti Menikah', 3, 1),
(5, 'Cuti Duka', 3, 1),
(6, 'Cuti Bersama', 1, 0);

CREATE TABLE IF NOT EXISTS pengajuan_cuti (
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
    INDEX idx_cuti_user (user_id),
    INDEX idx_cuti_status (status)
);

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pengajuan_cuti' AND COLUMN_NAME='dokumen');
SET @sql := IF(@exists=0, 'ALTER TABLE pengajuan_cuti ADD COLUMN dokumen VARCHAR(255) NULL AFTER alasan', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================
-- 3. Lembur
-- ============================================================

CREATE TABLE IF NOT EXISTS lembur (
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
    INDEX idx_lembur_user (user_id),
    INDEX idx_lembur_status (status),
    INDEX idx_lembur_tanggal (tanggal)
);

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='lembur' AND COLUMN_NAME='durasi_menit');
SET @sql := IF(@exists=0, 'ALTER TABLE lembur ADD COLUMN durasi_menit INT DEFAULT 0 AFTER jam_selesai', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='lembur' AND COLUMN_NAME='alasan');
SET @sql := IF(@exists=0, 'ALTER TABLE lembur ADD COLUMN alasan TEXT AFTER durasi_menit', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='lembur' AND COLUMN_NAME='upah_lembur');
SET @sql := IF(@exists=0, 'ALTER TABLE lembur ADD COLUMN upah_lembur DECIMAL(15,2) DEFAULT 0 AFTER catatan_approver', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Isi durasi_menit dari durasi_jam jika pernah dibuat oleh script lama.
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='lembur' AND COLUMN_NAME='durasi_jam');
SET @sql := IF(@exists>0, 'UPDATE lembur SET durasi_menit=COALESCE(durasi_menit, ROUND(durasi_jam*60)) WHERE durasi_menit IS NULL OR durasi_menit=0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='lembur' AND COLUMN_NAME='keterangan');
SET @sql := IF(@exists>0, 'UPDATE lembur SET alasan=COALESCE(alasan, keterangan) WHERE alasan IS NULL OR alasan=''''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================
-- 4. Reimburse
-- ============================================================

CREATE TABLE IF NOT EXISTS reimburse (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tanggal DATE NOT NULL,
    kategori VARCHAR(100) NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL,
    keterangan TEXT NOT NULL,
    bukti VARCHAR(255),
    status ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
    disetujui_oleh INT NULL,
    catatan_approver TEXT NULL,
    tanggal_approve DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reimburse_user (user_id),
    INDEX idx_reimburse_status (status)
);

-- ============================================================
-- 5. Chat
-- ============================================================

CREATE TABLE IF NOT EXISTS chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100),
    tipe ENUM('private','group') DEFAULT 'private',
    dibuat_oleh INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_rooms_tipe (tipe)
);

CREATE TABLE IF NOT EXISTS chat_room_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    last_read_at TIMESTAMP NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_member (room_id, user_id),
    INDEX idx_member_room (room_id),
    INDEX idx_member_user (user_id)
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    sender_id INT NOT NULL,
    pesan TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_messages_room (room_id),
    INDEX idx_messages_sender (sender_id)
);

INSERT IGNORE INTO chat_rooms (id, nama, tipe, dibuat_oleh)
SELECT 1, 'HR General', 'group', u.id
FROM users u
WHERE u.role = 'admin'
LIMIT 1;

INSERT IGNORE INTO chat_room_members (room_id, user_id)
SELECT 1, u.id
FROM users u
WHERE u.status = 'aktif';

-- ============================================================
-- 6. Payroll manual columns
-- ============================================================

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='slip_gaji' AND COLUMN_NAME='bonus');
SET @sql := IF(@exists=0, 'ALTER TABLE slip_gaji ADD COLUMN bonus DECIMAL(15,2) DEFAULT 0 AFTER tunjangan_transport', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='slip_gaji' AND COLUMN_NAME='potongan_lain');
SET @sql := IF(@exists=0, 'ALTER TABLE slip_gaji ADD COLUMN potongan_lain DECIMAL(15,2) DEFAULT 0 AFTER potongan_pph21', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='slip_gaji' AND COLUMN_NAME='catatan');
SET @sql := IF(@exists=0, 'ALTER TABLE slip_gaji ADD COLUMN catatan TEXT NULL AFTER dibuat_oleh', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================
-- 7. Notifications
-- ============================================================

CREATE TABLE IF NOT EXISTS notifikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    judul VARCHAR(200) NOT NULL,
    pesan TEXT NOT NULL,
    tipe ENUM('info','sukses','peringatan','bahaya') DEFAULT 'info',
    sudah_dibaca TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_user (user_id),
    INDEX idx_notif_read (sudah_dibaca)
);
