// ============================================================
// SIMK PHA — Main JS
// ============================================================

// ---- PWA Install ----
let deferredPrompt;
window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    const banner = document.getElementById('install-banner');
    if (banner) banner.classList.add('show');
});

document.getElementById('btn-install')?.addEventListener('click', async () => {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    if (outcome === 'accepted') document.getElementById('install-banner').classList.remove('show');
    deferredPrompt = null;
});

document.getElementById('btn-install-dismiss')?.addEventListener('click', () => {
    document.getElementById('install-banner').classList.remove('show');
});

// ---- Service Worker ----
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/pwa-hr/sw.js').then(reg => {
        console.log('SW registered:', reg.scope);
    }).catch(console.error);
}

// ---- Clock ----
function updateClock() {
    const el = document.getElementById('live-clock');
    if (!el) return;
    const now = new Date();
    el.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
    const dateEl = document.getElementById('live-date');
    if (dateEl) {
        const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        dateEl.textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
    }
}
if (document.getElementById('live-clock')) {
    updateClock();
    setInterval(updateClock, 1000);
}

// ---- GPS Location ----
let gpsLat = null, gpsLng = null, gpsAkurasi = null, gpsValid = false;
const OFFICE_LAT  = parseFloat(document.getElementById('office-lat')?.value  || '0');
const OFFICE_LNG  = parseFloat(document.getElementById('office-lng')?.value  || '0');
const ABSEN_RADIUS = parseFloat(document.getElementById('absen-radius')?.value || '200');

function hitungJarak(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function updateLocationUI(lat, lng, akurasi) {
    gpsLat = lat; gpsLng = lng; gpsAkurasi = akurasi;
    const jarak = OFFICE_LAT ? Math.round(hitungJarak(lat, lng, OFFICE_LAT, OFFICE_LNG)) : 0;
    gpsValid = OFFICE_LAT ? jarak <= ABSEN_RADIUS : true;

    const el = document.getElementById('location-status');
    const distEl = document.getElementById('location-distance');
    const latEl = document.getElementById('gps-lat');
    const lngEl = document.getElementById('gps-lng');

    if (latEl) latEl.value = lat;
    if (lngEl) lngEl.value = lng;

    if (el) {
        if (gpsValid) {
            el.className = 'location-status loc-valid';
            el.innerHTML = `<span class="pulse-dot"></span> Lokasi valid — ${jarak}m dari kantor`;
        } else {
            el.className = 'location-status loc-invalid';
            el.innerHTML = `<span class="pulse-dot"></span> Di luar area — ${jarak}m dari kantor (maks. ${ABSEN_RADIUS}m)`;
        }
    }
    if (distEl) distEl.textContent = `${jarak}m | Akurasi ±${Math.round(akurasi)}m`;

    // Enable/disable absen button
    const btnMasuk   = document.getElementById('btn-absen-masuk');
    const btnKeluar  = document.getElementById('btn-absen-keluar');
    if (btnMasuk)  { btnMasuk.disabled  = !gpsValid; btnMasuk.classList.toggle('absen-btn-disabled', !gpsValid); }
    if (btnKeluar) { btnKeluar.disabled = !gpsValid; btnKeluar.classList.toggle('absen-btn-disabled', !gpsValid); }
}

function initGPS() {
    const el = document.getElementById('location-status');
    if (!el) return;
    if (!navigator.geolocation) {
        el.className = 'location-status loc-invalid';
        el.innerHTML = '<span class="pulse-dot"></span> GPS tidak tersedia di perangkat ini';
        return;
    }
    el.className = 'location-status loc-loading';
    el.innerHTML = '<span class="pulse-dot"></span> Mendapatkan lokasi...';

    navigator.geolocation.watchPosition(
        pos => updateLocationUI(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy),
        err => {
            el.className = 'location-status loc-invalid';
            const msgs = { 1: 'Akses lokasi ditolak', 2: 'Lokasi tidak tersedia', 3: 'Timeout GPS' };
            el.innerHTML = `<span class="pulse-dot"></span> ${msgs[err.code] || 'GPS error'}`;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 10000 }
    );
}
if (document.getElementById('location-status')) initGPS();

// ---- Sidebar Mobile ----
document.getElementById('btn-sidebar-toggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
});
document.getElementById('sidebar-overlay')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
});

// ---- Modal helpers ----
window.openModal  = id => document.getElementById(id)?.classList.add('open');
window.closeModal = id => document.getElementById(id)?.classList.remove('open');

document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

// ---- Confirm delete ----
window.confirmDel = (url, nama) => {
    if (confirm(`Hapus "${nama}"?\nTindakan ini tidak bisa dibatalkan.`)) location.href = url;
};

// ---- Auto-submit filter selects ----
document.querySelectorAll('.auto-submit').forEach(s => s.addEventListener('change', () => s.closest('form').submit()));

// ---- Auto-hide alerts ----
document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => { a.style.transition = 'opacity 0.5s'; a.style.opacity = '0'; }, 4000);
});

// ---- Absen form submission with foto ----
function submitAbsen(tipe) {
    if (!gpsValid && OFFICE_LAT) {
        alert('Anda berada di luar area kantor. Absensi tidak dapat dilakukan.');
        return;
    }
    const form = document.getElementById('form-absen');
    if (!form) return;
    document.getElementById('hidden-tipe').value = tipe;
    document.getElementById('hidden-lat').value  = gpsLat;
    document.getElementById('hidden-lng').value  = gpsLng;
    form.submit();
}

// ---- Salary calculator ----
function hitungGaji() {
    const pokok   = parseFloat(document.getElementById('calc-pokok')?.value   || 0);
    const tunj    = parseFloat(document.getElementById('calc-tunj')?.value    || 0);
    const lembur  = parseFloat(document.getElementById('calc-lembur')?.value  || 0);
    const potAlpha= parseFloat(document.getElementById('calc-alpha')?.value   || 0);
    const bpjsTK  = pokok * 0.02;
    const bpjsKes = pokok * 0.01;
    const total   = pokok + tunj + lembur;
    const totalPot = potAlpha + bpjsTK + bpjsKes;
    const bersih  = total - totalPot;
    const fmt = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
    if (document.getElementById('calc-bpjs-tk'))  document.getElementById('calc-bpjs-tk').textContent  = fmt(bpjsTK);
    if (document.getElementById('calc-bpjs-kes')) document.getElementById('calc-bpjs-kes').textContent = fmt(bpjsKes);
    if (document.getElementById('calc-bersih'))   document.getElementById('calc-bersih').textContent   = fmt(bersih);
}
document.querySelectorAll('.calc-input').forEach(i => i.addEventListener('input', hitungGaji));

// ---- Cuti date range ----
function hitungHariCuti() {
    const m = document.getElementById('cuti-mulai')?.value;
    const s = document.getElementById('cuti-selesai')?.value;
    if (!m || !s) return;
    const ms = new Date(m), se = new Date(s);
    if (se < ms) { alert('Tanggal selesai harus setelah tanggal mulai'); return; }
    const hari = Math.round((se - ms) / 86400000) + 1;
    const el = document.getElementById('jumlah-hari');
    if (el) el.textContent = hari + ' hari';
    const hEl = document.getElementById('hidden-hari');
    if (hEl) hEl.value = hari;
}
document.getElementById('cuti-mulai')?.addEventListener('change', hitungHariCuti);
document.getElementById('cuti-selesai')?.addEventListener('change', hitungHariCuti);

// ---- Print slip ----
window.printSlip = () => {
    const el = document.getElementById('print-area');
    const w = window.open('', '_blank');
    w.document.write(`<html><head><title>Slip Gaji</title><style>
        body{font-family:sans-serif;font-size:13px;color:#000;}
        table{width:100%;border-collapse:collapse;}
        td,th{padding:6px 8px;border:1px solid #ccc;font-size:12px;}
        .bold{font-weight:700;} .right{text-align:right;}
        h2{margin:0 0 4px;} h4{margin:0 0 2px;color:#555;}
        .section{margin:12px 0;}
    </style></head><body>${el.innerHTML}</body></html>`);
    w.document.close(); w.print();
};
