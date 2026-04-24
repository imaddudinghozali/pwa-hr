<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();

// ── Jika request download, output CSV / Excel ────────────────
if (isset($_GET['download'])) {
    $fmt    = sanitize($_GET['fmt'] ?? 'csv');
    $deptF  = (int)($_GET['dept']   ?? 0);
    $jenisF = sanitize($_GET['jenis'] ?? '');
    $stF    = sanitize($_GET['status'] ?? '');

    $where = ["u.role IN ('karyawan','hrd')"];
    if ($deptF)  $where[] = "u.departemen_id=$deptF";
    if ($jenisF) $where[] = "u.jenis_karyawan='".esc($jenisF)."'";
    if ($stF)    $where[] = "u.status='".esc($stF)."'";
    $w = implode(' AND ', $where);

    $rows = db()->query("SELECT u.nip, u.nama, u.email, u.telepon,
        u.jenis_kelamin, u.jenis_karyawan, u.status, u.role,
        u.tanggal_bergabung, u.tanggal_kontrak_selesai, u.tanggal_lahir,
        u.alamat, u.no_rekening, u.nama_bank, u.sisa_cuti,
        d.nama AS departemen, j.nama AS jabatan,
        j.gaji_pokok, j.tunjangan_jabatan,
        COALESCE(u.gaji_pokok_override, j.gaji_pokok) AS gaji_efektif,
        COALESCE(u.tunjangan_jabatan_override, j.tunjangan_jabatan) AS tunjangan_efektif,
        s.nama AS shift
        FROM users u
        LEFT JOIN departemen d ON u.departemen_id=d.id
        LEFT JOIN jabatan    j ON u.jabatan_id=j.id
        LEFT JOIN shift      s ON u.shift_id=s.id
        WHERE $w ORDER BY d.nama, u.nama");

    $cols = [
        'NIP','Nama','Email','Telepon','Jenis Kelamin','Jenis Karyawan','Status','Role',
        'Departemen','Jabatan','Shift','Tgl Bergabung','Tgl Kontrak Selesai','Tgl Lahir',
        'Gaji Efektif','Tunjangan Efektif','Sisa Cuti','No Rekening','Nama Bank','Alamat'
    ];

    if ($fmt === 'xls') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="data_karyawan_'.date('Ymd').'.xls"');
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
        echo '<table border="1" style="border-collapse:collapse">';
        echo '<tr style="background:#166534;color:#fff;font-weight:bold">';
        foreach ($cols as $c) echo '<th>'.htmlspecialchars($c).'</th>';
        echo '</tr>';
        if ($rows) while ($r = $rows->fetch_assoc()) {
            $jk = $r['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan';
            echo '<tr>';
            $vals = [
                $r['nip'], $r['nama'], $r['email'], $r['telepon'],
                $jk, ucfirst($r['jenis_karyawan']??'tetap'), ucfirst($r['status']), strtoupper($r['role']),
                $r['departemen']??'', $r['jabatan']??'', $r['shift']??'',
                $r['tanggal_bergabung']??'', $r['tanggal_kontrak_selesai']??'',
                $r['tanggal_lahir']??'',
                (int)($r['gaji_efektif']??0), (int)($r['tunjangan_efektif']??0),
                $r['sisa_cuti']??0, $r['no_rekening']??'', $r['nama_bank']??'', $r['alamat']??''
            ];
            foreach ($vals as $v) echo '<td>'.htmlspecialchars((string)$v).'</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    } else {
        // CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="data_karyawan_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM untuk Excel
        $out = fopen('php://output', 'w');
        fputcsv($out, $cols, ';');
        if ($rows) while ($r = $rows->fetch_assoc()) {
            $jk = $r['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan';
            fputcsv($out, [
                $r['nip'], $r['nama'], $r['email'], $r['telepon'],
                $jk, ucfirst($r['jenis_karyawan']??'tetap'), ucfirst($r['status']), strtoupper($r['role']),
                $r['departemen']??'', $r['jabatan']??'', $r['shift']??'',
                $r['tanggal_bergabung']??'', $r['tanggal_kontrak_selesai']??'',
                $r['tanggal_lahir']??'',
                (int)($r['gaji_efektif']??0), (int)($r['tunjangan_efektif']??0),
                $r['sisa_cuti']??0, $r['no_rekening']??'', $r['nama_bank']??'', $r['alamat']??''
            ], ';');
        }
        fclose($out);
        exit;
    }
}

// ── Halaman preview ───────────────────────────────────────────
$deptF  = (int)($_GET['dept']   ?? 0);
$jenisF = sanitize($_GET['jenis'] ?? '');
$stF    = sanitize($_GET['status'] ?? '');

$where = ["u.role IN ('karyawan','hrd')"];
if ($deptF)  $where[] = "u.departemen_id=$deptF";
if ($jenisF) $where[] = "u.jenis_karyawan='".esc($jenisF)."'";
if ($stF)    $where[] = "u.status='".esc($stF)."'";
$w = implode(' AND ', $where);

$total = (int)db()->query("SELECT COUNT(*) c FROM users u WHERE $w")->fetch_assoc()['c'];
$rows  = db()->query("SELECT u.nip,u.nama,u.email,u.telepon,u.jenis_kelamin,
    u.jenis_karyawan,u.status,u.tanggal_bergabung,u.tanggal_kontrak_selesai,
    d.nama dept_nama, j.nama jabatan_nama,
    COALESCE(u.gaji_pokok_override, j.gaji_pokok) gaji_eff,
    COALESCE(u.tunjangan_jabatan_override, j.tunjangan_jabatan) tunj_eff
    FROM users u
    LEFT JOIN departemen d ON u.departemen_id=d.id
    LEFT JOIN jabatan j ON u.jabatan_id=j.id
    WHERE $w ORDER BY d.nama, u.nama LIMIT 200");

$deptList = getDepartemen();

$pageTitle  = 'Export Data Karyawan';
$activePage = 'export';
$baseQuery  = http_build_query(['dept'=>$deptF,'jenis'=>$jenisF,'status'=>$stF,'download'=>1]);
$topbarActions = '
<a href="?'.$baseQuery.'&fmt=xls" class="btn btn-primary">⬇ Export Excel (.xls)</a>
<a href="?'.$baseQuery.'&fmt=csv" class="btn btn-sm">⬇ Export CSV</a>';
include __DIR__.'/../../includes/header.php';
?>

<form method="GET" style="margin-bottom:1rem">
<div class="toolbar">
    <div class="toolbar-left">
        <select name="dept" class="sel-filter auto-submit">
            <option value="">Semua Departemen</option>
            <?php foreach ($deptList as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $deptF==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['nama']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="jenis" class="sel-filter auto-submit">
            <option value="">Semua Jenis</option>
            <option value="tetap"   <?= $jenisF==='tetap'  ?'selected':'' ?>>Tetap</option>
            <option value="kontrak" <?= $jenisF==='kontrak'?'selected':'' ?>>Kontrak</option>
            <option value="magang"  <?= $jenisF==='magang' ?'selected':'' ?>>Magang</option>
        </select>
        <select name="status" class="sel-filter auto-submit">
            <option value="">Semua Status</option>
            <option value="aktif"    <?= $stF==='aktif'   ?'selected':'' ?>>Aktif</option>
            <option value="nonaktif" <?= $stF==='nonaktif'?'selected':'' ?>>Non-aktif</option>
        </select>
        <?php if ($deptF||$jenisF||$stF): ?><a href="?" class="btn btn-sm">Reset</a><?php endif; ?>
    </div>
    <div class="toolbar-right">
        <span class="text-muted text-sm"><?= $total ?> karyawan</span>
        <button type="submit" class="btn btn-sm">Filter</button>
    </div>
</div>
</form>

<div class="alert alert-info" style="font-size:12.5px">
    Preview data yang akan diexport. Klik <strong>Export Excel</strong> atau <strong>Export CSV</strong> di atas untuk mengunduh.
    File CSV dapat dibuka dengan Excel (pastikan pilih delimiter titik-koma).
</div>

<div class="card">
<div class="tbl-wrap">
<table>
    <thead><tr>
        <th>NIP</th><th>Nama</th><th>Departemen</th><th>Jabatan</th><th>Jenis</th>
        <th>Status</th><th>Tgl Bergabung</th><th>Kontrak s/d</th>
        <th>Gaji Efektif</th><th>Email</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows || $rows->num_rows===0): ?>
    <tr><td colspan="10" style="text-align:center;padding:3rem;color:var(--text-m)">Tidak ada data</td></tr>
    <?php else: while ($r = $rows->fetch_assoc()):
        $bjk = ['tetap'=>'badge-green','kontrak'=>'badge-amber','magang'=>'badge-blue'];
        $bst = ['aktif'=>'badge-green','nonaktif'=>'badge-gray','cuti'=>'badge-amber'];
    ?>
    <tr>
        <td class="mono text-sm"><?= htmlspecialchars($r['nip']) ?></td>
        <td><div class="nc-name"><?= htmlspecialchars($r['nama']) ?></div></td>
        <td class="text-sm"><?= htmlspecialchars($r['dept_nama'] ?? '—') ?></td>
        <td class="text-sm"><?= htmlspecialchars($r['jabatan_nama'] ?? '—') ?></td>
        <td><span class="badge <?= $bjk[$r['jenis_karyawan']??'tetap']??'badge-gray' ?>"><?= ucfirst($r['jenis_karyawan']??'tetap') ?></span></td>
        <td><span class="badge <?= $bst[$r['status']]??'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
        <td class="text-sm"><?= formatTgl($r['tanggal_bergabung'] ?? '') ?></td>
        <td class="text-sm">
            <?= !empty($r['tanggal_kontrak_selesai']) ? formatTgl($r['tanggal_kontrak_selesai']) : '—' ?>
        </td>
        <td class="mono text-sm text-green"><?= formatRp((float)($r['gaji_eff']??0)) ?></td>
        <td class="text-sm text-muted"><?= htmlspecialchars($r['email'] ?? '—') ?></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>
</div>
<?php if ($total > 200): ?>
<div style="padding:10px 14px;font-size:12px;color:var(--amber);border-top:1px solid var(--border)">
    ⚠ Preview dibatasi 200 baris. File export akan berisi semua <?= $total ?> data.
</div>
<?php endif; ?>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
