<?php
session_start();
require_once __DIR__.'/../config/database.php';
requireLogin();

header('Content-Type: application/json');
$me   = currentUser();
$uid  = (int)$me['id'];
$act  = sanitize($_REQUEST['action'] ?? '');

// ── Helper ────────────────────────────────────────────────────
function jsonOut(array $data): void { echo json_encode($data); exit; }

// ── Daftar rooms yang diikuti user ────────────────────────────
if ($act === 'get_rooms') {
    $rows = db()->query("
        SELECT cr.id, cr.nama, cr.tipe,
            (SELECT cm.pesan FROM chat_messages cm WHERE cm.room_id=cr.id ORDER BY cm.created_at DESC LIMIT 1) last_msg,
            (SELECT cm.created_at FROM chat_messages cm WHERE cm.room_id=cr.id ORDER BY cm.created_at DESC LIMIT 1) last_at,
            (SELECT u.nama FROM chat_messages cm JOIN users u ON cm.sender_id=u.id WHERE cm.room_id=cr.id ORDER BY cm.created_at DESC LIMIT 1) last_sender,
            (SELECT COUNT(*) FROM chat_messages cm2 WHERE cm2.room_id=cr.id
                AND cm2.sender_id!=$uid
                AND (crm2.last_read_at IS NULL OR cm2.created_at > crm2.last_read_at)
            ) unread
        FROM chat_rooms cr
        JOIN chat_room_members crm ON crm.room_id=cr.id AND crm.user_id=$uid
        JOIN chat_room_members crm2 ON crm2.room_id=cr.id AND crm2.user_id=$uid
        ORDER BY last_at DESC, cr.id ASC
    ");
    $rooms = [];
    if ($rows) while ($r = $rows->fetch_assoc()) $rooms[] = $r;
    jsonOut(['ok'=>true,'rooms'=>$rooms]);
}

// ── Dapatkan atau buat private room antara 2 user ─────────────
if ($act === 'get_or_create_room') {
    $targetId = (int)($_POST['target_id'] ?? 0);
    if (!$targetId) jsonOut(['ok'=>false,'msg'=>'target_id required']);

    // Cari private room yang ada kedua member ini
    $r = db()->query("
        SELECT cr.id FROM chat_rooms cr
        JOIN chat_room_members m1 ON m1.room_id=cr.id AND m1.user_id=$uid
        JOIN chat_room_members m2 ON m2.room_id=cr.id AND m2.user_id=$targetId
        WHERE cr.tipe='private'
        AND (SELECT COUNT(*) FROM chat_room_members WHERE room_id=cr.id) = 2
        LIMIT 1
    ")->fetch_assoc();

    if ($r) {
        jsonOut(['ok'=>true,'room_id'=>(int)$r['id']]);
    } else {
        $target = db()->query("SELECT nama FROM users WHERE id=$targetId LIMIT 1")->fetch_assoc();
        $nama1  = esc($me['nama']); $nama2 = esc($target['nama'] ?? '');
        db()->query("INSERT INTO chat_rooms (nama,tipe,dibuat_oleh) VALUES ('$nama1 & $nama2','private',$uid)");
        $rid = (int)db()->insert_id;
        db()->query("INSERT INTO chat_room_members (room_id,user_id) VALUES ($rid,$uid),($rid,$targetId)");
        jsonOut(['ok'=>true,'room_id'=>$rid]);
    }
}

// ── Ambil pesan dalam room ────────────────────────────────────
if ($act === 'get_messages') {
    $roomId  = (int)($_GET['room_id'] ?? 0);
    $lastId  = (int)($_GET['last_id'] ?? 0);
    if (!$roomId) jsonOut(['ok'=>false,'msg'=>'room_id required']);

    // Pastikan user adalah member room ini
    $mem = db()->query("SELECT id FROM chat_room_members WHERE room_id=$roomId AND user_id=$uid LIMIT 1")->fetch_assoc();
    if (!$mem) jsonOut(['ok'=>false,'msg'=>'Tidak diizinkan']);

    $sql = "SELECT cm.id, cm.sender_id, cm.pesan,
        DATE_FORMAT(cm.created_at,'%H:%i') jam,
        DATE_FORMAT(cm.created_at,'%d/%m/%Y') tgl,
        cm.created_at,
        u.nama sender_nama
        FROM chat_messages cm JOIN users u ON cm.sender_id=u.id
        WHERE cm.room_id=$roomId";
    if ($lastId) $sql .= " AND cm.id > $lastId";
    $sql .= " ORDER BY cm.id ASC LIMIT 100";

    $msgs = [];
    $rows = db()->query($sql);
    if ($rows) while ($m = $rows->fetch_assoc()) {
        $m['is_me'] = (int)$m['sender_id'] === $uid;
        $msgs[] = $m;
    }

    // Update last_read
    db()->query("UPDATE chat_room_members SET last_read_at=NOW() WHERE room_id=$roomId AND user_id=$uid");

    // Info room & members
    $roomInfo = db()->query("SELECT cr.*,
        GROUP_CONCAT(u.nama ORDER BY u.nama SEPARATOR ', ') members
        FROM chat_rooms cr
        JOIN chat_room_members crm ON crm.room_id=cr.id
        JOIN users u ON crm.user_id=u.id
        WHERE cr.id=$roomId GROUP BY cr.id LIMIT 1")->fetch_assoc();

    jsonOut(['ok'=>true,'messages'=>$msgs,'room'=>$roomInfo,'my_id'=>$uid]);
}

// ── Kirim pesan ───────────────────────────────────────────────
if ($act === 'send_message') {
    $roomId = (int)($_POST['room_id'] ?? 0);
    $pesan  = trim($_POST['pesan']    ?? '');
    if (!$roomId || !$pesan) jsonOut(['ok'=>false,'msg'=>'Data tidak lengkap']);

    $mem = db()->query("SELECT id FROM chat_room_members WHERE room_id=$roomId AND user_id=$uid LIMIT 1")->fetch_assoc();
    if (!$mem) jsonOut(['ok'=>false,'msg'=>'Tidak diizinkan']);

    $pesan_e = esc(substr($pesan, 0, 2000));
    db()->query("INSERT INTO chat_messages (room_id,sender_id,pesan) VALUES ($roomId,$uid,'$pesan_e')");
    $newId = (int)db()->insert_id;

    jsonOut(['ok'=>true,'id'=>$newId]);
}

// ── Daftar user untuk mulai chat baru ─────────────────────────
if ($act === 'get_users') {
    $rows = db()->query("SELECT id,nama,role,jabatan_id,
        (SELECT j.nama FROM jabatan j WHERE j.id=users.jabatan_id) jabatan_nama
        FROM users WHERE status='aktif' AND id!=$uid ORDER BY nama");
    $users = [];
    if ($rows) while ($u = $rows->fetch_assoc()) $users[] = $u;
    jsonOut(['ok'=>true,'users'=>$users]);
}

jsonOut(['ok'=>false,'msg'=>'Unknown action']);
