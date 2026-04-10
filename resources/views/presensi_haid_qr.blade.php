@php
    // Sama seperti presensi_sholat_qr: RFID aktif, QR disembunyikan (USE_QR_SCANNER di script).
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Haid — RFID</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <style>
        * { box-sizing: border-box; }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Poppins', system-ui, sans-serif;
            background: #fff7ff;
            display: flex;
            justify-content: center;
        }
        .app {
            width: 100%;
            max-width: 520px;
            min-height: 100vh;
            background: #fff7ff;
            color: #2b2340;
            position: relative;
        }
        .drawer-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 30;
        }
        .drawer {
            position: fixed;
            top: 0; left: 0;
            width: 272px; height: 100%;
            background: linear-gradient(180deg, #0f172a 0%, #020617 100%);
            color: #f9fafb;
            transform: translateX(-100%);
            transition: transform 0.2s ease;
            padding: 24px 20px;
            display: flex;
            flex-direction: column;
            z-index: 40;
            border-right: 1px solid rgba(255,255,255,0.06);
            box-shadow: 4px 0 24px rgba(0,0,0,0.2);
        }
        .drawer.open { transform: translateX(0); }
        .drawer-backdrop.open { opacity: 1; pointer-events: auto; }
        .drawer-header { display: flex; align-items: center; margin-bottom: 20px; }
        .drawer-logo { width: 50px; height: 50px; border-radius: 999px; overflow: hidden; margin-right: 10px; }
        .drawer-logo img { width: 100%; height: 100%; object-fit: contain; }
        .drawer-user-name { font-size: 0.95rem; font-weight: 600; }
        .drawer-user-role { font-size: 0.78rem; opacity: 0.75; }
        .drawer-divider { height: 1px; background: #4b5563; margin: 16px 0; }
        .drawer-menu { list-style: none; padding: 0; margin: 0; flex: 1; }
        .drawer-menu-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin-bottom: 10px; padding-left: 14px; }
        .drawer-item { margin-bottom: 12px; }
        .drawer-link {
            display: flex; align-items: center;
            padding: 12px 14px; border-radius: 999px;
            color: inherit; text-decoration: none;
            font-size: 0.9rem; transition: background 0.15s ease;
        }
        .drawer-link span.icon { width: 26px; display: inline-flex; justify-content: center; align-items: center; margin-right: 12px; }
        .drawer-link span.icon i { font-size: 1.05rem; color: #e2e8f0 !important; }
        .drawer-link:hover { background: rgba(148,163,184,0.18); }
        .drawer-link.active { background: rgba(148,163,184,0.15); }
        .drawer-footer { font-size: 0.78rem; opacity: 0.7; margin-top: 12px; }
        .header { display: flex; align-items: center; padding: 16px 16px 10px; justify-content: space-between; }
        .left-header { display: flex; align-items: center; gap: 12px; }
        .burger { width: 28px; border: none; background: transparent; padding: 0; cursor: pointer; }
        .burger span { display: block; height: 3px; border-radius: 999px; background: #7c3aed; margin-bottom: 5px; }
        .title { font-size: 1.1rem; font-weight: 600; color: #a855f7; }
        .status { display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; color: #64748b; }
        .dot { width: 9px; height: 9px; border-radius: 999px; background: #22c55e; box-shadow: 0 0 0 4px rgba(34,197,94,0.12); }
        .content { padding: 0; }
        .scanner-wrap {
            position: relative;
            width: 100%;
            height: calc(100vh - 92px);
            background: #000;
            overflow: hidden;
        }
        #video {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: cover;
        }
        #canvas { display: none; }
        .scan-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }
        .scan-box {
            position: relative;
            width: min(78vw, 78vh, 400px);
            height: min(78vw, 78vh, 400px);
        }
        .scan-box::before,
        .scan-box::after,
        .scan-box .c3,
        .scan-box .c4 {
            content: '';
            position: absolute;
            width: 32px; height: 32px;
            border-color: #a855f7;
            border-style: solid;
        }
        .scan-box::before  { top: 0;    left: 0;    border-width: 4px 0 0 4px; }
        .scan-box::after   { top: 0;    right: 0;   border-width: 4px 4px 0 0; }
        .scan-box .c3      { bottom: 0; left: 0;    border-width: 0 0 4px 4px; }
        .scan-box .c4      { bottom: 0; right: 0;   border-width: 0 4px 4px 0; }
        .scan-line {
            position: absolute;
            left: 4px; right: 4px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #a855f7, transparent);
            animation: scanline 2s linear infinite;
        }
        @keyframes scanline {
            0%   { top: 4px;   opacity: 1; }
            90%  { top: calc(100% - 6px); opacity: 1; }
            100% { top: calc(100% - 6px); opacity: 0; }
        }
        .hint {
            padding: 10px 4px;
            font-size: 0.9rem;
            color: #6b607f;
            text-align: center;
            background: #fff7ff;
        }
        .qr-scanner-hidden {
            display: none !important;
        }
        .rfid-panel-hidden {
            display: none !important;
        }
        .rfid-panel {
            min-height: calc(100vh - 92px);
            padding: 28px 20px 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 18px;
            background: linear-gradient(180deg, #faf5ff 0%, #fff7ff 100%);
        }
        .rfid-panel-icon {
            font-size: 2.75rem;
            color: #a855f7;
            line-height: 1;
        }
        .rfid-panel-title {
            font-size: 1rem;
            font-weight: 600;
            color: #5b4d78;
            text-align: center;
            max-width: 320px;
        }
        .rfid-input-wrap {
            width: 100%;
            max-width: 360px;
        }
        #rfidInput {
            width: 100%;
            padding: 16px 18px;
            font-size: 1.2rem;
            font-family: ui-monospace, 'Cascadia Code', monospace;
            border: 2px solid #e9d5ff;
            border-radius: 14px;
            background: #fff;
            color: #2b2340;
            outline: none;
            box-shadow: 0 4px 18px rgba(168, 85, 247, 0.1);
        }
        #rfidInput:focus {
            border-color: #a855f7;
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.22);
        }
        .rfid-caption {
            font-size: 0.8rem;
            color: #8b7aa3;
            text-align: center;
            max-width: 340px;
            line-height: 1.45;
        }
        @media (min-width: 960px) {
            body { justify-content: flex-start; }
            .app { max-width: none; width: calc(100% - 272px); margin-left: 272px; }
            .drawer { position: fixed; transform: translateX(0); }
            .drawer-backdrop { display: none; }
            .burger { display: none; }
        }
    </style>
</head>
<body>
<div class="app">
    <div class="header">
        <div class="left-header">
            <button class="burger" id="drawerToggle" type="button" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <div class="title">Presensi Haid</div>
        </div>
        <div class="status" id="status">
            <span class="dot"></span>
            <span>Ready</span>
        </div>
    </div>

    <div class="content">
        <div id="qrScannerSection" class="scanner-wrap qr-scanner-hidden" aria-hidden="true">
            <video id="video" autoplay playsinline muted></video>
            <canvas id="canvas"></canvas>
            <div class="scan-overlay">
                <div class="scan-box">
                    <div class="c3"></div>
                    <div class="c4"></div>
                    <div class="scan-line"></div>
                </div>
            </div>
        </div>
        <div class="rfid-panel" id="rfidPanel">
            <div class="rfid-panel-icon" aria-hidden="true"><i class="fas fa-microchip"></i></div>
            <div class="rfid-panel-title">Tempelkan kartu pada pembaca RFID</div>
            <div class="rfid-input-wrap">
                <label for="rfidInput" class="sr-only">Input nomor kartu RFID</label>
                <input type="text" id="rfidInput" name="rfid" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="text" aria-label="Nomor kartu RFID">
            </div>
            <p class="rfid-caption">Pembaca yang bertindak sebagai keyboard akan mengisi kolom ini dan mengirim otomatis setelah scan (biasanya tombol Enter).</p>
        </div>
        <div class="hint" id="hint">Menunggu tap RFID…</div>
    </div>
</div>

<div class="drawer-backdrop" id="drawerBackdrop"></div>
<aside class="drawer" id="drawer">
    <div class="drawer-header">
        <div class="drawer-logo">
            <img src="{{ asset('icon.png') }}" alt="Logo">
        </div>
        <div>
            <div class="drawer-user-name">{{ session('user.username', '-') }}</div>
            <div class="drawer-user-role">User</div>
        </div>
    </div>
    <div class="drawer-divider"></div>
    <div class="drawer-menu-label">Presensi Sholat</div>
    <ul class="drawer-menu">
        <li class="drawer-item">
            <a href="{{ route('presensi-sholat.qr') }}" class="drawer-link">
                <span class="icon"><i class="fas fa-qrcode"></i></span>
                <span>Presensi Sholat</span>
            </a>
        </li>
        @if(config('presensi.show_haid'))
        <li class="drawer-item">
            <a href="{{ route('presensi-haid.qr') }}" class="drawer-link active">
                <span class="icon"><i class="fas fa-qrcode"></i></span>
                <span>Presensi Haid</span>
            </a>
        </li>
        @endif
        <li class="drawer-item">
            <a href="{{ route('presensi.log-marifah') }}" class="drawer-link">
                <span class="icon"><i class="fas fa-rectangle-list"></i></span>
                <span>Log Marifah</span>
            </a>
        </li>
        <li class="drawer-item">
            <a href="{{ route('presensi.log-presensi') }}" class="drawer-link">
                <span class="icon"><i class="fas fa-square-check"></i></span>
                <span>Log Presensi</span>
            </a>
        </li>
        <li class="drawer-item">
            <a href="{{ route('presensi.kelola') }}" class="drawer-link">
                <span class="icon"><i class="fas fa-arrows-rotate"></i></span>
                <span>Kelola Presensi</span>
            </a>
        </li>
        <li class="drawer-item">
            <a href="{{ route('presensi.rekap-sholat') }}" class="drawer-link">
                <span class="icon"><i class="fas fa-chart-simple"></i></span>
                <span>Rekap Sholat</span>
            </a>
        </li>
        <li class="drawer-item">
            <a href="{{ route('presensi.account.ganti-password') }}" class="drawer-link">
                <span class="icon"><i class="fas fa-gear"></i></span>
                <span>Account Controls</span>
            </a>
        </li>
        <li class="drawer-item">
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="drawer-link" style="width:100%;text-align:left;border:none;background:transparent;color:inherit;cursor:pointer;font-size:inherit;font-family:inherit;padding:12px 14px;">
                    <span class="icon"><i class="fas fa-right-from-bracket"></i></span>
                    <span>Log Out</span>
                </button>
            </form>
        </li>
    </ul>
    <div class="drawer-footer">App Ver : 1.0.0 — Al-Izzah Batu</div>
</aside>

<script>
    const USE_QR_SCANNER = false;
    const POST_PRESENSI_URL = @json(route('presensi-haid.post-haid'));
    const HINT_QR = 'Arahkan kamera ke QR Code';
    const HINT_RFID_IDLE = 'Menunggu tap RFID…';

    const toggleBtn = document.getElementById('drawerToggle');
    const backdrop  = document.getElementById('drawerBackdrop');
    const drawer    = document.getElementById('drawer');
    function closeDrawer() { drawer.classList.remove('open'); backdrop.classList.remove('open'); }
    toggleBtn.addEventListener('click', function() {
        drawer.classList.contains('open') ? closeDrawer() : (drawer.classList.add('open'), backdrop.classList.add('open'));
    });
    backdrop.addEventListener('click', closeDrawer);

    const statusEl = document.getElementById('status');
    const hintEl   = document.getElementById('hint');
    const video    = document.getElementById('video');
    const canvas   = document.getElementById('canvas');
    const rfidInput = document.getElementById('rfidInput');
    const qrSection = document.getElementById('qrScannerSection');

    let stream    = null;
    let rafId     = null;
    let isPosting = false;
    let isScanning = false;

    function setStatus(text, isReady) {
        var c = isReady ? '#22c55e' : '#f59e0b';
        var s = isReady ? '34,197,94' : '245,158,11';
        statusEl.innerHTML = '<span class="dot" style="background:' + c + ';box-shadow:0 0 0 4px rgba(' + s + ',0.12)"></span><span>' + text + '</span>';
    }

    function focusRfid() {
        if (!rfidInput || USE_QR_SCANNER) return;
        rfidInput.value = '';
        try { rfidInput.focus({ preventScroll: true }); } catch (e) { rfidInput.focus(); }
    }

    async function submitPresensi(nokartu) {
        var res = await fetch(POST_PRESENSI_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token()),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ nokartu: nokartu })
        });

        var json = {};
        try { json = await res.json(); } catch (e) {}

        if (res.ok && json.ok) {
            await Swal.fire({ icon: 'success', title: 'Berhasil', text: json.message || 'Presensi haid berhasil.', confirmButtonColor: '#a855f7' });
        } else {
            await Swal.fire({ icon: 'error', title: 'Gagal', text: (json && json.message) ? json.message : 'Presensi gagal (HTTP ' + res.status + ').', confirmButtonColor: '#a855f7' });
        }
    }

    function tick() {
        if (!isScanning) return;
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width  = video.videoWidth;
            canvas.height = video.videoHeight;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            var code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
            if (code && code.data) {
                onScanSuccess(code.data);
                return;
            }
        }
        rafId = requestAnimationFrame(tick);
    }

    async function startScanner() {
        if (stream) return;
        setStatus('Starting...', false);
        hintEl.textContent = 'Meminta izin kamera...';

        var constraints = {
            video: {
                facingMode: { ideal: 'environment' },
                width:  { ideal: 1280 },
                height: { ideal: 720 }
            }
        };

        try {
            stream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch(err1) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
            } catch(err2) {
                setStatus('Camera blocked', false);
                hintEl.textContent = 'Izin kamera ditolak.';
                Swal.fire({
                    icon: 'error',
                    title: 'Izin Kamera Ditolak',
                    html: 'Klik ikon <b>kunci/kamera</b> di address bar, pilih <b>Izinkan</b>, lalu refresh.',
                    confirmButtonColor: '#a855f7'
                });
                return;
            }
        }

        video.srcObject = stream;
        video.play();
        isScanning = true;
        setStatus('Ready', true);
        hintEl.textContent = HINT_QR;
        rafId = requestAnimationFrame(tick);
    }

    function stopScanner() {
        isScanning = false;
        if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
        if (stream) {
            stream.getTracks().forEach(function(t){ t.stop(); });
            stream = null;
        }
        video.srcObject = null;
    }

    async function onScanSuccess(decodedText) {
        if (isPosting) return;
        var nokartu = (decodedText || '').trim();
        if (!nokartu) return;

        isPosting = true;
        isScanning = false;
        if (rafId) { cancelAnimationFrame(rafId); rafId = null; }

        setStatus('Processing...', false);
        hintEl.textContent = 'Terbaca: ' + nokartu + ' (mengirim...)';

        try {
            await submitPresensi(nokartu);
        } catch (e) {
            await Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal mengirim data. Coba lagi.', confirmButtonColor: '#a855f7' });
        } finally {
            isPosting = false;
            setStatus('Ready', true);
            if (USE_QR_SCANNER) {
                hintEl.textContent = HINT_QR;
                isScanning = true;
                rafId = requestAnimationFrame(tick);
            } else {
                hintEl.textContent = HINT_RFID_IDLE;
                focusRfid();
            }
        }
    }

    if (rfidInput && !USE_QR_SCANNER) {
        rfidInput.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            if (isPosting) return;
            var v = (rfidInput.value || '').trim();
            if (!v) return;

            isPosting = true;
            setStatus('Processing...', false);
            hintEl.textContent = 'Terbaca: ' + v + ' (mengirim...)';

            (async function () {
                try {
                    await submitPresensi(v);
                } catch (err) {
                    await Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal mengirim data. Coba lagi.', confirmButtonColor: '#a855f7' });
                } finally {
                    isPosting = false;
                    setStatus('Ready', true);
                    hintEl.textContent = HINT_RFID_IDLE;
                    focusRfid();
                }
            })();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var rfidPanel = document.getElementById('rfidPanel');
        if (USE_QR_SCANNER) {
            if (rfidPanel) rfidPanel.classList.add('rfid-panel-hidden');
            if (qrSection) {
                qrSection.classList.remove('qr-scanner-hidden');
                qrSection.setAttribute('aria-hidden', 'false');
            }
            startScanner();
        } else {
            setStatus('Ready', true);
            hintEl.textContent = HINT_RFID_IDLE;
            focusRfid();
        }
    });

    window.addEventListener('focus', function () {
        if (!USE_QR_SCANNER && !isPosting) focusRfid();
    });
</script>
</body>
</html>

