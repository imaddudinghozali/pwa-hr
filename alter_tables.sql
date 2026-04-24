-- ============================================================
-- SIMK PHA — Migrasi Fitur Baru
-- Jalankan setelah database utama (database.sql) sudah di-import
-- ============================================================

USE simk_pha;

-- ── 0. Kolom kepala pada tabel departemen (fix bug lama) ─────
ALTER TABLE departemen
    ADD COLUMN IF NOT EXISTS kepala VARCHAR(100) NULL AFTER radius_absen;

-- ── 1. Jenis karyawan & kontrak pada tabel users ─────────────
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS jenis_karyawan ENUM('tetap','kontrak','magang') DEFAULT 'tetap' AFTER status,
    ADD COLUMN IF NOT EXISTS tanggal_kontrak_selesai DATE NULL AFTER tanggal_bergabung,
    ADD COLUMN IF NOT EXISTS gaji_pokok_override DECIMAL(15,2) DEFAULT NULL COMMENT 'Override gaji pokok dari jabatan',
    ADD COLUMN IF NOT EXISTS tunjangan_jabatan_override DECIMAL(15,2) DEFAULT NULL COMMENT 'Override tunjangan jabatan';

-- ── 2. Tabel Reimburse ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reimburse (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tanggal DATE NOT NULL,
    kategori VARCHAR(100) NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL,
    keterangan TEXT NOT NULL,
    bukti VARCHAR(255) COMMENT 'Nama file bukti foto',
    status ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
    disetujui_oleh INT,
    catatan_approver TEXT,
    tanggal_approve DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (disetujui_oleh) REFERENCES users(id) ON DELETE SET NULL
);

-- ── 3. Tabel Chat ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100),
    tipe ENUM('private','group') DEFAULT 'private',
    dibuat_oleh INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS chat_room_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    last_read_at TIMESTAMP NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_member (room_id, user_id),
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    sender_id INT NOT NULL,
    pesan TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── 4. Folder upload (pastikan sudah ada) ─────────────────────
-- Buat folder: assets/uploads/absensi/ dan assets/uploads/reimburse/
-- secara manual atau via PHP

-- ── 5. Grup default "HR General" ─────────────────────────────
INSERT IGNORE INTO chat_rooms (id, nama, tipe, dibuat_oleh)
SELECT 1, 'HR General', 'group', u.id FROM users u WHERE u.role='admin' LIMIT 1;

-- Masukkan semua karyawan aktif ke grup HR General
INSERT IGNORE INTO chat_room_members (room_id, user_id)
SELECT 1, u.id FROM users u WHERE u.status='aktif';
