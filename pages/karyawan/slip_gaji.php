<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireLogin();
$user = currentUser();
if ($user['role'] !== 'karyawan') redirect(BASE_URL.'/pages/karyawan/dashboard.php');
$uid = (int)$user['id'];

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

$slipList = db()->query("SELECT bulan,tahun,gaji_bersih,status FROM slip_gaji WHERE user_id=$uid ORDER BY tahun DESC,bulan DESC")->fetch_all(MYSQLI_ASSOC);

$slip = null;
if (!empty($slipList)) {
    $slip = db()->query("SELECT sg.*,u.nama,u.nip,u.no_rekening,u.nama_bank,
        d.nama dept_nama,j.nama jabatan_nama,s.nama shift_nama
        FROM slip_gaji sg JOIN users u ON sg.user_id=u.id
        LEFT JOIN departemen d ON u.departemen_id=d.id
        LEFT JOIN jabatan j ON u.jabatan_id=j.id
        LEFT JOIN shift s ON u.shift_id=s.id
        WHERE sg.user_id=$uid AND sg.bulan=$bulan AND sg.tahun=$tahun LIMIT 1")->fetch_assoc();
}

$pageTitle     = 'Slip Gaji Saya';
$activePage    = 'gaji';
$topbarActions = $slip ? '<button class="btn btn-sm" onclick="printSlip()">🖨 Cetak</button>' : '';
include __DIR__.'/../../includes/header.php';
?>

<div class="grid-2" style="grid-template-columns:240px 1fr;align-items:start">

    <!-- Daftar slip -->
    <div class="card">
        <div class="card-header"><span class="card-title">Slip Tersedia</span></div>
        <div style="padding:6px 0">
        <?php if (empty($slipList)): ?>
        <div style="padding:1.5rem;text-align:center;color:var(--text-m);font-size:13px">Belum ada slip gaji</div>
        <?php else: foreach ($slipList as $s):
            $aktif = $s['bulan']==$bulan && $s['tahun']==$tahun;
            $bsSt  = ['draft'=>'badge-amber','final'=>'badge-blue','dibayar'=>'badge-green'];
        ?>
        <a href="?bulan=<?=$s['bulan']?>&tahun=<?=$s['tahun']?>"
            style="display:flex;align-items:center;justify-content:space-between;
                   padding:10px 14px;border-bottom:1px solid var(--border);text-decoration:none;
                   <?=$aktif?'background:rgba(34,197,94,.1)':''?>">
            <div>
                <div style="font-size:13px;font-weight:600;color:<?=$aktif?'var(--green-400)':'var(--text-1)'?>">
                    <?=bulanNama($s['bulan'])?> <?=$s['tahun']?>
                </div>
                <div style="font-size:11px;color:var(--text-m)"><?=formatRp($s['gaji_bersih'])?></div>
            </div>
            <span class="badge <?=$bsSt[$s['status']]??'badge-gray'?>"><?=ucfirst($s['status'])?></span>
        </a>
        <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Detail slip -->
    <div>
    <?php if (!$slip): ?>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:3rem;color:var(--text-m)">
            <div style="font-size:48px;margin-bottom:12px">📄</div>
            <div>Pilih periode dari daftar kiri untuk melihat slip gaji.</div>
        </div>
    </div>
    <?php else:
        $tunj = (float)$slip['tunjangan_jabatan']+(float)$slip['tunjangan_makan']+(float)$slip['tunjangan_transport'];
        $pot  = (float)$slip['potongan_absen']+(float)$slip['potongan_bpjs_tk']+(float)$slip['potongan_bpjs_kes']+(float)$slip['potongan_pph21'];
        $bsSt = ['draft'=>'badge-amber','final'=>'badge-blue','dibayar'=>'badge-green'];
    ?>
    <div id="print-area">
        <!-- Header -->
        <div class="slip-header mb-2">
            <div class="slip-header-top">
                <div>
                    <div class="slip-company">PT Pesta Hijau Abadi</div>
                    <div class="slip-period">Slip Gaji — <?=bulanNama($slip['bulan'])?> <?=$slip['tahun']?></div>
                </div>
                <span class="badge <?=$bsSt[$slip['status']]??'badge-gray'?>"><?=ucfirst($slip['status'])?></span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">
                <?php foreach ([
                    ['Nama',$slip['nama']],['NIP',$slip['nip']],
                    ['Jabatan',$slip['jabatan_nama']??'—'],['Departemen',$slip['dept_nama']??'—'],
                    ['No. Rekening',$slip['no_rekening']??'—'],['Bank',$slip['nama_bank']??'—']
                ] as [$k,$v]): ?>
                <div><span class="text-muted text-xs"><?=$k?></span><br><strong><?=htmlspecialchars($v)?></strong></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Kehadiran -->
        <div class="card mb-2">
            <div class="card-header"><span class="card-title">Kehadiran</span></div>
            <div class="card-body" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;text-align:center">
                <?php foreach ([
                    ['Hadir',$slip['hari_hadir'],'var(--green-400)'],
                    ['Alpha',$slip['hari_alpha'],'var(--red)'],
                    ['Jam Lembur',$slip['total_lembur_jam'],'var(--blue)']
                ] as [$k,$v,$c]): ?>
                <div style="padding:12px;background:var(--surface-2);border-radius:var(--r)">
                    <div style="font-size:22px;font-weight:700;color:<?=$c?>"><?=$v?></div>
                    <div class="text-xs text-muted"><?=$k?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Rincian -->
        <div class="card">
            <div class="card-header"><span class="card-title">Rincian Gaji</span></div>
            <div class="card-body">
                <div style="margin-bottom:1rem">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--green-400);margin-bottom:8px">+ PENDAPATAN</div>
                    <?php $inc=[['Gaji Pokok',$slip['gaji_pokok'],''],['Tunjangan Jabatan',$slip['tunjangan_jabatan'],''],['Tunjangan Makan',$slip['tunjangan_makan'],''],['Tunjangan Transport',$slip['tunjangan_transport'],'']];
                    if ((float)$slip['upah_lembur']>0) $inc[]=['Upah Lembur ('.$slip['total_lembur_jam'].' jam)',$slip['upah_lembur'],'text-blue'];
                    foreach ($inc as [$k,$v,$cls]): ?>
                    <div class="slip-row"><span><?=$k?></span><span class="<?=$cls?>"><?=formatRp($v)?></span></div>
                    <?php endforeach; ?>
                    <div class="slip-row" style="font-weight:600;border-top:1px solid var(--border-md);padding-top:8px;margin-top:4px">
                        <span>Jumlah Pendapatan</span><strong><?=formatRp((float)$slip['gaji_pokok']+$tunj+(float)$slip['upah_lembur'])?></strong>
                    </div>
                </div>
                <div style="margin-bottom:1rem">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#fca5a5;margin-bottom:8px">− POTONGAN</div>
                    <?php $ded=[['Potongan Ketidakhadiran',$slip['potongan_absen']],['BPJS TK (2%)',$slip['potongan_bpjs_tk']],['BPJS Kes (1%)',$slip['potongan_bpjs_kes']],['PPh 21 (5%)',$slip['potongan_pph21']]];
                    foreach ($ded as [$k,$v]): if ((float)$v>0): ?>
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
                <div style="margin-top:10px;text-align:right;font-size:12px;color:var(--text-m)">
                    Dibayar: <?=formatTgl($slip['tanggal_bayar'])?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- /#print-area -->
    <?php endif; ?>
    </div>
</div>

<?php include __DIR__.'/../../includes/footer.php'; ?>
