<?php
session_start();
require_once __DIR__.'/../../config/database.php';
requireAdmin();
$user = currentUser();
$uid  = (int)$user['id'];

$pageTitle  = 'Chat';
$activePage = 'chat';
include __DIR__.'/../../includes/header.php';
?>

<style>
.chat-layout { display:grid; grid-template-columns:300px 1fr; gap:0; height:calc(100vh - var(--topbar-h) - 3rem); min-height:400px; }
.chat-sidebar { background:var(--surface); border:1px solid var(--border); border-radius:var(--rl) 0 0 var(--rl); overflow-y:auto; display:flex; flex-direction:column; }
.chat-main { background:var(--surface); border:1px solid var(--border); border-left:none; border-radius:0 var(--rl) var(--rl) 0; display:flex; flex-direction:column; }
.chat-sidebar-header { padding:.875rem 1rem; border-bottom:1px solid var(--border); font-weight:700; font-size:14px; display:flex; justify-content:space-between; align-items:center; flex-shrink:0; }
.room-item { padding:.75rem 1rem; cursor:pointer; border-bottom:1px solid var(--border); transition:background .15s; }
.room-item:hover { background:var(--surface-2); }
.room-item.active { background:rgba(34,197,94,.1); border-right:2px solid var(--green-600); }
.room-name { font-size:13.5px; font-weight:600; color:var(--text-1); }
.room-preview { font-size:11.5px; color:var(--text-m); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:220px; }
.room-badge { background:var(--red); color:#fff; border-radius:100px; font-size:10px; padding:1px 6px; font-weight:700; flex-shrink:0; }
.chat-messages { flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:8px; }
.chat-header { padding:.875rem 1rem; border-bottom:1px solid var(--border); font-weight:700; font-size:14px; display:flex; align-items:center; gap:8px; }
.chat-input-wrap { padding:.75rem 1rem; border-top:1px solid var(--border); display:flex; gap:8px; flex-shrink:0; }
.chat-input { flex:1; padding:9px 14px; background:var(--surface-2); border:1px solid var(--border-md); border-radius:var(--rl); color:var(--text-1); font-family:inherit; font-size:13.5px; resize:none; height:42px; max-height:120px; }
.chat-input:focus { outline:none; border-color:var(--green-600); }
.bubble { max-width:68%; padding:8px 14px; border-radius:14px; font-size:13.5px; line-height:1.5; word-break:break-word; }
.bubble.mine { background:var(--green-800); color:var(--green-100); align-self:flex-end; border-radius:14px 14px 4px 14px; }
.bubble.other { background:var(--surface-2); color:var(--text-1); align-self:flex-start; border-radius:14px 14px 14px 4px; }
.bubble-meta { font-size:10px; color:var(--text-m); margin-top:3px; }
.bubble.mine .bubble-meta { text-align:right; }
.no-room { display:flex; align-items:center; justify-content:center; flex:1; color:var(--text-m); font-size:14px; text-align:center; padding:2rem; }
.tab-btn { padding:6px 14px; border-radius:100px; font-size:12px; font-weight:600; cursor:pointer; background:none; border:1px solid var(--border-md); color:var(--text-2); transition:all .15s; }
.tab-btn.active { background:var(--green-700); border-color:var(--green-600); color:#fff; }
@media (max-width:700px) { .chat-layout { grid-template-columns:1fr; } .chat-sidebar { border-radius:var(--rl) var(--rl) 0 0; max-height:200px; } .chat-main { border-left:1px solid var(--border); border-radius:0 0 var(--rl) var(--rl); min-height:380px; } }
</style>

<div class="chat-layout">
    <!-- Sidebar -->
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <span>Percakapan</span>
            <button class="btn btn-sm btn-primary" onclick="openNewChat()" style="padding:4px 10px;font-size:12px">+ Baru</button>
        </div>
        <div style="padding:8px 10px;border-bottom:1px solid var(--border);display:flex;gap:6px;flex-shrink:0">
            <button class="tab-btn active" id="tab-all"  onclick="setTab('all')">Semua</button>
            <button class="tab-btn"       id="tab-group" onclick="setTab('group')">Grup</button>
            <button class="tab-btn"       id="tab-priv"  onclick="setTab('private')">Pribadi</button>
        </div>
        <div id="room-list" style="flex:1;overflow-y:auto"></div>
    </div>

    <!-- Chat area -->
    <div class="chat-main">
        <div class="no-room" id="no-room-msg">
            Pilih percakapan atau mulai chat baru dengan karyawan
        </div>
        <div id="chat-area" style="display:none;flex-direction:column;flex:1;overflow:hidden">
            <div class="chat-header">
                <span id="chat-header-name">—</span>
                <span id="chat-header-badge" class="badge badge-blue" style="font-size:11px;display:none"></span>
            </div>
            <div class="chat-messages" id="chat-messages"></div>
            <div class="chat-input-wrap">
                <textarea id="chat-input" class="chat-input" placeholder="Ketik pesan..."
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();kirimPesan();}"></textarea>
                <button class="btn btn-primary" onclick="kirimPesan()" style="padding:9px 18px">Kirim</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal new chat -->
<div class="modal-overlay" id="mNewChat">
<div class="modal">
    <div class="modal-header"><span class="modal-title">Mulai Chat Dengan Karyawan</span><button class="modal-close" onclick="closeModal('mNewChat')">✕</button></div>
    <div class="modal-body">
        <div class="form-group mb-2">
            <input type="text" id="search-user" class="form-control" placeholder="Cari nama / jabatan..." oninput="filterUsers()">
        </div>
        <div id="user-list" style="max-height:320px;overflow-y:auto"></div>
    </div>
</div>
</div>

<script>
var BASE         = '<?= BASE_URL ?>';
var activeRoomId = 0;
var lastMsgId    = 0;
var pollTimer    = null;
var currentTab   = 'all';
var allRooms     = [];

function setTab(t) {
    currentTab = t;
    ['all','group','priv'].forEach(function(b){
        document.getElementById('tab-'+b).className = 'tab-btn' + (b===t||('private'===t&&b==='priv')?t===b||('private'===t&&b==='priv')?' active':'':'');
    });
    // quick fix
    document.getElementById('tab-all').className   = 'tab-btn'+(t==='all'   ?' active':'');
    document.getElementById('tab-group').className = 'tab-btn'+(t==='group' ?' active':'');
    document.getElementById('tab-priv').className  = 'tab-btn'+(t==='private'?' active':'');
    renderRooms(allRooms);
}

function loadRooms() {
    fetch(BASE+'/api/chat.php?action=get_rooms')
    .then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        allRooms = d.rooms || [];
        renderRooms(allRooms);
    });
}

function renderRooms(rooms) {
    var filtered = rooms.filter(function(r){
        if (currentTab==='group')   return r.tipe==='group';
        if (currentTab==='private') return r.tipe==='private';
        return true;
    });
    var html = '';
    filtered.forEach(function(rm){
        var unread  = parseInt(rm.unread)||0;
        var cls     = (rm.id == activeRoomId) ? 'room-item active' : 'room-item';
        var preview = rm.last_msg ? (rm.last_sender?rm.last_sender+': ':'')+rm.last_msg.substring(0,55) : 'Belum ada pesan';
        html += '<div class="'+cls+'" onclick="bukaRoom('+rm.id+',\''+escHtml(rm.nama)+'\',\''+rm.tipe+'\')">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;gap:6px">' +
            '<div class="room-name">'+(rm.tipe==='group'?'👥 ':'')+escHtml(rm.nama)+'</div>' +
            (unread>0?'<span class="room-badge">'+unread+'</span>':'')+
            '</div><div class="room-preview">'+escHtml(preview)+'</div></div>';
    });
    if (!html) html = '<div style="text-align:center;padding:2rem;color:var(--text-m);font-size:13px">Tidak ada percakapan</div>';
    document.getElementById('room-list').innerHTML = html;
}

function bukaRoom(rid, rname, rtipe) {
    activeRoomId = rid; lastMsgId = 0;
    document.getElementById('no-room-msg').style.display = 'none';
    var ca = document.getElementById('chat-area');
    ca.style.display = 'flex';
    document.getElementById('chat-header-name').textContent = (rtipe==='group'?'👥 ':'')+rname;
    var badge = document.getElementById('chat-header-badge');
    badge.style.display = rtipe==='group'?'':'none';
    badge.textContent = 'Grup';
    document.getElementById('chat-messages').innerHTML = '';
    clearInterval(pollTimer);
    pollMessages();
    pollTimer = setInterval(pollMessages, 3000);
    loadRooms();
}

function pollMessages() {
    if (!activeRoomId) return;
    fetch(BASE+'/api/chat.php?action=get_messages&room_id='+activeRoomId+'&last_id='+lastMsgId)
    .then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var box = document.getElementById('chat-messages');
        var atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;
        d.messages.forEach(function(m){
            if (document.querySelector('[data-msgid="'+m.id+'"]')) return;
            var cls = m.is_me ? 'bubble mine' : 'bubble other';
            var sLabel = m.is_me ? '' : '<div style="font-size:11px;font-weight:700;color:var(--green-400);margin-bottom:2px">'+escHtml(m.sender_nama)+'</div>';
            var div = document.createElement('div');
            div.setAttribute('data-msgid', m.id);
            div.innerHTML = '<div class="'+cls+'">'+sLabel+escHtml(m.pesan)+'<div class="bubble-meta">'+m.jam+'</div></div>';
            box.appendChild(div);
            lastMsgId = Math.max(lastMsgId, parseInt(m.id));
        });
        if (atBottom) box.scrollTop = box.scrollHeight;
    });
}

function kirimPesan() {
    var inp = document.getElementById('chat-input');
    var p   = inp.value.trim();
    if (!p || !activeRoomId) return;
    inp.value = '';
    var fd = new FormData();
    fd.append('action','send_message'); fd.append('room_id',activeRoomId); fd.append('pesan',p);
    fetch(BASE+'/api/chat.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.ok) pollMessages(); });
}

var allUsers = [];
function openNewChat() {
    fetch(BASE+'/api/chat.php?action=get_users')
    .then(r=>r.json()).then(d=>{ allUsers=d.users||[]; renderUserList(allUsers); openModal('mNewChat'); });
}
function filterUsers() {
    var q = document.getElementById('search-user').value.toLowerCase();
    renderUserList(allUsers.filter(u=>u.nama.toLowerCase().includes(q)||(u.jabatan_nama||'').toLowerCase().includes(q)||(u.role||'').toLowerCase().includes(q)));
}
function renderUserList(users) {
    var html = '';
    users.forEach(function(u){
        var roleLabel = {karyawan:'Karyawan',hrd:'HRD',admin:'Admin'}[u.role]||u.role;
        html += '<div style="padding:10px 8px;cursor:pointer;border-radius:var(--r);display:flex;align-items:center;gap:10px" '+
            'onmouseover="this.style.background=\'var(--surface-2)\'" onmouseout="this.style.background=\'\'" '+
            'onclick="startPrivateChat('+u.id+',\''+escHtml(u.nama)+'\')">' +
            '<div class="avatar av-sm" style="background:#16a34a;flex-shrink:0">'+initials(u.nama)+'</div>' +
            '<div><div style="font-size:13px;font-weight:600">'+escHtml(u.nama)+'</div>' +
            '<div style="font-size:11px;color:var(--text-m)">'+roleLabel+' · '+(escHtml(u.jabatan_nama)||'—')+'</div></div></div>';
    });
    if (!html) html = '<div style="text-align:center;padding:1rem;color:var(--text-m)">Tidak ada user</div>';
    document.getElementById('user-list').innerHTML = html;
}
function startPrivateChat(targetId, targetNama) {
    closeModal('mNewChat');
    var fd = new FormData();
    fd.append('action','get_or_create_room'); fd.append('target_id',targetId);
    fetch(BASE+'/api/chat.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) { loadRooms(); bukaRoom(d.room_id, targetNama, 'private'); }
    });
}
function initials(n){ var p=n.trim().split(' ').filter(Boolean); return (p[0][0]+(p[1]?p[1][0]:'')).toUpperCase(); }
function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

loadRooms();
setInterval(loadRooms, 8000);
</script>

<?php include __DIR__.'/../../includes/footer.php'; ?>
