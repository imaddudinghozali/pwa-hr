<?php
session_start();
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../includes/xlsx_reader.php';
requireAdmin();

function importNorm(string $value): string {
    $value = str_replace("\xc2\xa0", ' ', $value);
    return trim(preg_replace('/\s+/', ' ', $value));
}

function importHeaderKey(string $value): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', importNorm($value)));
}

function importMoney(string $value): ?float {
    $value = trim($value);
    if ($value === '') return null;
    $value = preg_replace('/[^0-9,.\-]/', '', $value);
    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } elseif (substr_count($value, ',') === 1 && !str_contains($value, '.')) {
        $value = str_replace(',', '.', $value);
    }
    return is_numeric($value) ? (float)$value : null;
}

function importDate(?string $value): ?string {
    $value = importNorm((string)$value);
    if ($value === '') return null;
    if (is_numeric($value)) {
        $serial = (float)$value;
        if ($serial > 0) {
            $base = new DateTimeImmutable('1899-12-30');
            return $base->modify('+'.(int)floor($serial).' days')->format('Y-m-d');
        }
    }

    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd M Y', 'd F Y'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) return $dt->format('Y-m-d');
    }

    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

function importStatus(string $value): string {
    $v = strtolower(str_replace([' ', '-'], '', importNorm($value)));
    return match ($v) {
        'nonaktif', 'nonactive', 'inactive', 'resign', 'keluar' => 'nonaktif',
        'cuti' => 'cuti',
        default => 'aktif',
    };
}

function importJenisKaryawan(string $value): string {
    $v = strtolower(importNorm($value));
    return in_array($v, ['kontrak', 'magang'], true) ? $v : 'tetap';
}

function importEnsureDepartemen(string $nama): ?int {
    $nama = importNorm($nama);
    if ($nama === '') return null;
    $namaEsc = esc($nama);
    $row = db()->query("SELECT id FROM departemen WHERE LOWER(nama)=LOWER('$namaEsc') LIMIT 1");
    if ($row && $found = $row->fetch_assoc()) return (int)$found['id'];

    $kodeBase = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($nama, 0, 12))) ?: 'DEPT';
    $kode = $kodeBase;
    $i = 1;
    while (true) {
        $kodeEsc = esc($kode);
        $exists = db()->query("SELECT id FROM departemen WHERE kode='$kodeEsc' LIMIT 1");
        if (!$exists || $exists->num_rows === 0) break;
        $kode = substr($kodeBase, 0, 10).$i;
        $i++;
    }

    db()->query("INSERT INTO departemen (nama,kode,radius_absen) VALUES ('$namaEsc','".esc($kode)."',100)");
    return db()->error ? null : (int)db()->insert_id;
}

function importEnsureJabatan(string $nama, ?int $deptId, ?float $gajiPokok, ?float $tunjangan): ?int {
    $nama = importNorm($nama);
    if ($nama === '') return null;
    $namaEsc = esc($nama);
    $whereDept = $deptId ? "departemen_id=$deptId" : 'departemen_id IS NULL';
    $row = db()->query("SELECT id FROM jabatan WHERE LOWER(nama)=LOWER('$namaEsc') AND $whereDept LIMIT 1");
    if ($row && $found = $row->fetch_assoc()) return (int)$found['id'];

    $deptSql = $deptId ? (string)$deptId : 'NULL';
    $gajiSql = $gajiPokok !== null ? (string)$gajiPokok : '0';
    $tunjSql = $tunjangan !== null ? (string)$tunjangan : '0';
    db()->query("INSERT INTO jabatan (nama,departemen_id,gaji_pokok,tunjangan_jabatan) VALUES ('$namaEsc',$deptSql,$gajiSql,$tunjSql)");
    return db()->error ? null : (int)db()->insert_id;
}

function importReadRows(string $path): array {
    $autoload = __DIR__.'/../../vendor/autoload.php';
    if (is_file($autoload)) require_once $autoload;

    if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName('ALL') ?: $spreadsheet->getSheet(0);
        return $sheet->toArray(null, true, true, true);
    }

    $reader = new MinimalXlsxReader($path);
    return $reader->sheetRows('ALL');
}

function importValue(array $row, array $headers, array $aliases): string {
    foreach ($aliases as $alias) {
        $key = importHeaderKey($alias);
        if (isset($headers[$key])) {
            return importNorm((string)($row[$headers[$key]] ?? ''));
        }
    }
    return '';
}

$result = $_SESSION['import_karyawan_result'] ?? null;
unset($_SESSION['import_karyawan_result']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = 0;
    $skipped = 0;

    if (empty($_FILES['excel']['tmp_name']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
        flash('error', 'File Excel wajib diupload.');
        redirect(BASE_URL.'/pages/admin/import_karyawan.php');
    }

    $name = $_FILES['excel']['name'] ?? '';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'], true)) {
        flash('error', 'Format file harus .xlsx atau .xls.');
        redirect(BASE_URL.'/pages/admin/import_karyawan.php');
    }

    try {
        $rows = importReadRows($_FILES['excel']['tmp_name']);
        if (count($rows) < 2) {
            throw new RuntimeException('File tidak memiliki data karyawan.');
        }

        $headerRow = array_shift($rows);
        $headers = [];
        foreach ($headerRow as $col => $label) {
            $key = importHeaderKey((string)$label);
            if ($key !== '') $headers[$key] = $col;
        }

        $requiredHeaders = ['kode', 'nama'];
        foreach ($requiredHeaders as $required) {
            if (!isset($headers[$required])) {
                throw new RuntimeException("Kolom wajib tidak ditemukan: $required");
            }
        }

        $defaultShiftId = null;
        $shift = db()->query("SELECT id FROM shift ORDER BY id LIMIT 1");
        if ($shift && $s = $shift->fetch_assoc()) $defaultShiftId = (int)$s['id'];
        $shiftSql = $defaultShiftId ? (string)$defaultShiftId : 'NULL';

        $seenNip = [];
        $seenEmail = [];
        foreach ($rows as $idx => $row) {
            $excelRow = $idx + 2;
            $nip = importValue($row, $headers, ['Kode', 'NIP']);
            $nama = importValue($row, $headers, ['Nama']);
            $jabatan = importValue($row, $headers, ['Posisi', 'Jabatan']);
            $dept = importValue($row, $headers, ['Dept', 'Departemen']);
            $telepon = importValue($row, $headers, ['No Telepon', 'Telepon', 'Phone']);
            $email = importValue($row, $headers, ['Email']);
            $noRek = importValue($row, $headers, ['No Rekening', 'Rekening']);
            $bank = importValue($row, $headers, ['Bank', 'Nama Bank']);
            $tglMasuk = importValue($row, $headers, ['Tanggal Masuk', 'Tanggal Bergabung']);
            $jenis = importValue($row, $headers, ['Jenis']);
            $lokasiKerja = importValue($row, $headers, ['Lokasi Kerja']);
            $status = importValue($row, $headers, ['Status']);
            $gajiPokok = importMoney(importValue($row, $headers, ['Gaji Pokok']));
            $tunjangan = importMoney(importValue($row, $headers, ['Tunjangan Pokok', 'Tunjangan Jabatan']));

            if ($nip === '' && $nama === '') {
                $skipped++;
                continue;
            }

            $rowErrors = [];
            if ($nip === '') $rowErrors[] = 'NIP/Kode kosong';
            if ($nama === '') $rowErrors[] = 'Nama kosong';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $rowErrors[] = 'Email tidak valid';
            if ($nip !== '' && isset($seenNip[strtolower($nip)])) $rowErrors[] = 'NIP duplicate di file';
            if ($email !== '' && isset($seenEmail[strtolower($email)])) $rowErrors[] = 'Email duplicate di file';
            if ($nip !== '') {
                $dup = db()->query("SELECT id FROM users WHERE nip='".esc($nip)."' LIMIT 1");
                if ($dup && $dup->num_rows > 0) $rowErrors[] = 'NIP sudah ada di database';
            }
            if ($email !== '') {
                $dupEmail = db()->query("SELECT id FROM users WHERE email='".esc($email)."' LIMIT 1");
                if ($dupEmail && $dupEmail->num_rows > 0) $rowErrors[] = 'Email sudah ada di database';
            }

            $tanggalBergabung = importDate($tglMasuk);
            if ($tglMasuk !== '' && !$tanggalBergabung) $rowErrors[] = 'Tanggal masuk tidak valid';

            if ($rowErrors) {
                $errors[] = ['row' => $excelRow, 'nip' => $nip, 'nama' => $nama, 'reason' => implode(', ', $rowErrors)];
                continue;
            }

            $seenNip[strtolower($nip)] = true;
            if ($email !== '') $seenEmail[strtolower($email)] = true;
            $deptId = importEnsureDepartemen($dept);
            $jabatanId = importEnsureJabatan($jabatan, $deptId, $gajiPokok, $tunjangan);

            $hash = password_hash('Karyawan@123', PASSWORD_BCRYPT, ['cost' => 10]);
            $tglSql = $tanggalBergabung ? "'".esc($tanggalBergabung)."'" : 'NULL';
            $emailSql = $email !== '' ? "'".esc($email)."'" : 'NULL';
            $deptSql = $deptId ? (string)$deptId : 'NULL';
            $jabSql = $jabatanId ? (string)$jabatanId : 'NULL';
            $gajiSql = $gajiPokok !== null ? (string)$gajiPokok : 'NULL';
            $tunjSql = $tunjangan !== null ? (string)$tunjangan : 'NULL';

            db()->query("INSERT INTO users
                (nip,nama,email,telepon,departemen_id,jabatan_id,shift_id,tanggal_bergabung,
                 status,role,jenis_karyawan,no_rekening,nama_bank,lokasi_kerja,password,
                 gaji_pokok_override,tunjangan_jabatan_override,sisa_cuti)
                VALUES
                ('".esc($nip)."','".esc($nama)."',$emailSql,'".esc($telepon)."',$deptSql,$jabSql,$shiftSql,$tglSql,
                 '".esc(importStatus($status))."','karyawan','".esc(importJenisKaryawan($jenis))."',
                 '".esc($noRek)."','".esc($bank)."','".esc($lokasiKerja)."','".esc($hash)."',
                 $gajiSql,$tunjSql,12)");

            if (db()->error) {
                $errors[] = ['row' => $excelRow, 'nip' => $nip, 'nama' => $nama, 'reason' => db()->error];
                continue;
            }

            $newId = (int)db()->insert_id;
            $room = db()->query("SELECT id FROM chat_rooms WHERE tipe='group' LIMIT 1");
            if ($room && $r = $room->fetch_assoc()) {
                db()->query("INSERT IGNORE INTO chat_room_members (room_id,user_id) VALUES (".(int)$r['id'].",$newId)");
            }
            $success++;
        }

        $_SESSION['import_karyawan_result'] = [
            'success' => $success,
            'failed' => count($errors),
            'skipped' => $skipped,
            'errors' => $errors,
        ];
        flash($success > 0 ? 'success' : 'error', "Import selesai: $success berhasil, ".count($errors).' gagal.');
    } catch (Throwable $e) {
        flash('error', 'Import gagal: '.$e->getMessage());
    }

    redirect(BASE_URL.'/pages/admin/import_karyawan.php');
}

$pageTitle = 'Import Data Karyawan';
$activePage = 'karyawan';
$topbarActions = '<a href="'.BASE_URL.'/pages/admin/karyawan.php" class="btn btn-sm icon-label"><span class="ui-icon i-arrow-left"></span> Kembali</a>';
include __DIR__.'/../../includes/header.php';
?>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><span class="card-title">Upload Excel Karyawan</span></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">File Excel (.xlsx / .xls)</label>
                    <input type="file" name="excel" class="form-control" accept=".xlsx,.xls" required>
                </div>
                <div class="alert alert-info">
                    Import memakai kolom: Kode, Nama, Posisi, Dept, No Telepon, Email, No Rekening, Bank, Tanggal Masuk, Jenis, Lokasi Kerja, Status.
                </div>
                <button type="submit" class="btn btn-primary">Import Karyawan</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Mapping Data</span></div>
        <div class="card-body">
            <div class="info-list">
                <div><strong>Kode</strong><span>NIP</span></div>
                <div><strong>Posisi</strong><span>Jabatan, otomatis dibuat jika belum ada</span></div>
                <div><strong>Dept</strong><span>Departemen, otomatis dibuat jika belum ada</span></div>
                <div><strong>Lokasi Kerja</strong><span>Disimpan ke kolom users.lokasi_kerja</span></div>
                <div><strong>No KTP, Atas Nama, Total Gaji, Catatan</strong><span>Diabaikan untuk menjaga schema tetap ramping</span></div>
            </div>
        </div>
    </div>
</div>

<?php if ($result): ?>
<div class="card mt-2">
    <div class="card-header"><span class="card-title">Hasil Import</span></div>
    <div class="card-body">
        <div class="stat-grid">
            <div class="stat-card green"><div class="stat-label">Berhasil</div><div class="stat-value"><?= (int)$result['success'] ?></div></div>
            <div class="stat-card red"><div class="stat-label">Gagal</div><div class="stat-value"><?= (int)$result['failed'] ?></div></div>
            <div class="stat-card blue"><div class="stat-label">Baris Kosong</div><div class="stat-value"><?= (int)$result['skipped'] ?></div></div>
        </div>
        <?php if (!empty($result['errors'])): ?>
        <div class="tbl-wrap mt-2">
            <table>
                <thead><tr><th>Baris</th><th>NIP</th><th>Nama</th><th>Alasan</th></tr></thead>
                <tbody>
                <?php foreach ($result['errors'] as $err): ?>
                <tr>
                    <td><?= (int)$err['row'] ?></td>
                    <td class="mono text-sm"><?= htmlspecialchars($err['nip']) ?></td>
                    <td><?= htmlspecialchars($err['nama']) ?></td>
                    <td><?= htmlspecialchars($err['reason']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__.'/../../includes/footer.php'; ?>
