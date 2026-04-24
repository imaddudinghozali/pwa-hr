<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();

$sid = (int)($_GET['id'] ?? 0);
if (!$sid) redirect(BASE_URL.'/pages/admin/penggajian.php');

$slip = db()->query("SELECT sg.*,u.nama,u.nip,u.no_rekening,u.nama_bank,u.telepon,d.nama dept_nama,j.nama jabatan_nama,s.nama shift_nama
    FROM slip_gaji sg JOIN users u ON sg.user_id=u.id
    LEFT JOIN departemen d ON u.departemen_id=d.id
    LEFT JOIN jabatan j ON u.jabatan_id=j.id
    LEFT JOIN shift s ON u.shift_id=s.id
    WHERE sg.id=$sid LIMIT 1")->fetch_assoc();
if (!$slip) redirect(BASE_URL.'/pages/admin/penggajian.php');

$pageTitle  = 'Detail Slip Gaji';
$pageSub    = bulanNama($slip['bulan']).' '.$slip['tahun'].' — '.htmlspecialchars($slip['nama']);
$activePage = 'penggajian';
$topbarActions = '<button class="btn btn-sm" onclick="printSlip()">🖨 Cetak</button> <a href="'.BASE_URL.'/pages/admin/penggajian.php" class="btn btn-sm">← Kembali</a>';
include __DIR__.'/../../includes/header.php';

$tunj = (float)$slip['tunjangan_jabatan']+(float)$slip['tunjangan_makan']+(float)$slip['tunjangan_transport'];
$pot  = (float)$slip['potongan_absen']+(float)$slip['potongan_bpjs_tk']+(float)$slip['potongan_bpjs_kes']+(float)$slip['potongan_pph21'];
$bsSt = ['draft'=>'badge-amber','final'=>'badge-blue','dibayar'=>'badge-green'];
?>

<div id="print-area" style="max-width:680px;margin:0 auto">
    <div class="slip-header mb-2">
        <div class="slip-header-top">
            <div>
                <div class="slip-company">PT Pesta Hijau Abadi</div>
                <div style="font-size:11px;color:var(--text-m)">info@pestahijau.co.id</div>
                <div class="slip-period">Slip Gaji — <?=bulanNama($slip['bulan']).' '.$slip['tahun']?></div>
            </div>
            <span class="badge <?=$bsSt[$slip['status']]??'badge-gray'?>"><?=ucfirst($slip['status'])?></span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">
            <?php foreach ([['Nama',$slip['nama']],['NIP',$slip['nip']],['Jabatan',$slip['jabatan_nama']??'—'],['Departemen',$slip['dept_nama']??'—'],['Rekening',$slip['no_rekening']??'—'],['Bank',$slip['nama_bank']??'—']] as [$k,$v]): ?>
            <div><span class="text-muted text-xs"><?=$k?></span><br><strong><?=htmlspecialchars($v)?></strong></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header"><span class="card-title">Kehadiran</span></div>
        <div class="card-body" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;text-align:center">
            <?php foreach ([['Hadir',$slip['hari_hadir'],'var(--green-400)'],['Hari Kerja',$slip['hari_kerja'],'var(--text-1)'],['Alpha',$slip['hari_alpha'],'var(--red)'],['Jam Lembur',$slip['total_lembur_jam'],'var(--blue)']] as [$k,$v,$c]): ?>
            <div style="padding:12px;background:var(--surface-2);border-radius:var(--r)">
                <div style="font-size:22px;font-weight:700;color:<?=$c?>"><?=$v?></div>
                <div class="text-xs text-muted"><?=$k?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Rincian Gaji</span></div>
        <div class="card-body">
            <div style="margin-bottom:1rem">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--green-400);margin-bottom:10px">+ PENDAPATAN</div>
                <?php $items=[['Gaji Pokok',$slip['gaji_pokok'],''],['Tunjangan Jabatan',$slip['tunjangan_jabatan'],''],['Tunjangan Makan',$slip['tunjangan_makan'],''],['Tunjangan Transport',$slip['tunjangan_transport'],'']];
                if ($slip['upah_lembur']>0) $items[]=['Upah Lembur ('.$slip['total_lembur_jam'].' jam)',$slip['upah_lembur'],'text-blue'];
                foreach ($items as [$k,$v,$cls]): ?>
                <div class="slip-row"><span><?=$k?></span><span class="<?=$cls?>"><?=formatRp($v)?></span></div>
                <?php endforeach; ?>
                <div class="slip-row" style="font-weight:600;border-top:1px solid var(--border-md);padding-top:8px;margin-top:4px">
                    <span>Jumlah Pendapatan</span><strong><?=formatRp((float)$slip['gaji_pokok']+$tunj+(float)$slip['upah_lembur'])?></strong>
                </div>
            </div>
            <div style="margin-bottom:1rem">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#fca5a5;margin-bottom:10px">− POTONGAN</div>
                <?php $pots=[['Pot. Ketidakhadiran',$slip['potongan_absen']],['BPJS TK (2%)',$slip['potongan_bpjs_tk']],['BPJS Kes (1%)',$slip['potongan_bpjs_kes']],['PPh 21 (5%)',$slip['potongan_pph21']]];
                foreach ($pots as [$k,$v]): if ($v>0): ?>
                <div class="slip-row"><span><?=$k?></span><span class="text-red"><?=formatRp($v)?></span></div>
                <?php endif; endforeach; ?>
                <div class="slip-row" style="font-weight:600;border-top:1px solid var(--border-md);padding-top:8px;margin-top:4px">
                    <span>Jumlah Potongan</span><strong class="text-red"><?=formatRp($pot)?></strong>
                </div>
            </div>
            <div class="slip-row total">
                <span>💰 GAJI BERSIH</span>
                <strong style="font-size:18px"><?=formatRp($slip['gaji_bersih'])?></strong>
            </div>
            <?php if ($slip['tanggal_bayar']): ?>
            <div style="margin-top:10px;text-align:right;font-size:12px;color:var(--text-m)">Dibayar: <?=formatTgl($slip['tanggal_bayar'])?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__.'/../../includes/footer.php'; ?>
