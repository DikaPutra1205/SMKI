<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pratinjau Template Email SMKI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background-color: #0f172a; color: #f8fafc; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Top Navigation */
        .topbar { background-color: #1e293b; border-bottom: 1px solid #334155; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; gap: 16px; }
        .logo { display: flex; align-items: center; gap: 10px; }
        .logo-badge { background: #0f172a; border: 1px solid #334155; color: #f8fafc; font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 4px; letter-spacing: 0.5px; }
        .logo-title { font-size: 13.5px; font-weight: 600; color: #ffffff; white-space: nowrap; }

        .top-controls { display: flex; align-items: center; gap: 12px; }

        /* Test Send Box */
        .send-test-box { display: flex; align-items: center; gap: 6px; background: #0f172a; padding: 4px 6px 4px 12px; border-radius: 6px; border: 1px solid #334155; }
        .send-test-input { background: transparent; border: none; color: #ffffff; font-size: 12px; width: 200px; outline: none; font-family: inherit; }
        .send-test-input::placeholder { color: #64748b; }
        .send-test-btn { background: #0284c7; border: none; color: #ffffff; font-size: 11.5px; font-weight: 600; padding: 6px 12px; border-radius: 4px; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
        .send-test-btn:hover { background: #0369a1; }
        .send-test-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .device-toggle { display: flex; align-items: center; gap: 4px; background: #0f172a; padding: 3px; border-radius: 6px; border: 1px solid #334155; }
        .device-btn { background: transparent; border: none; color: #94a3b8; padding: 4px 10px; font-size: 11.5px; font-weight: 500; border-radius: 4px; cursor: pointer; transition: all 0.15s; }
        .device-btn.active { background: #334155; color: #ffffff; font-weight: 600; }

        /* Main Workspace */
        .workspace { display: flex; flex: 1; overflow: hidden; }

        /* Sidebar Navigation */
        .sidebar { width: 340px; background: #0f172a; border-right: 1px solid #1e293b; overflow-y: auto; padding: 20px 16px; flex-shrink: 0; display: flex; flex-direction: column; gap: 20px; }
        .module-group { display: flex; flex-direction: column; gap: 6px; }
        .module-title { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; padding: 0 6px; margin-bottom: 2px; }

        .template-card { background: #1e293b; border: 1px solid transparent; padding: 12px 14px; border-radius: 6px; cursor: pointer; transition: all 0.15s; display: flex; flex-direction: column; gap: 4px; text-decoration: none; }
        .template-card:hover { border-color: #475569; }
        .template-card.active { background: #1e293b; border-color: #38bdf8; }

        .card-header { display: flex; align-items: center; justify-content: space-between; }
        .card-tag { font-size: 10.5px; font-weight: 600; padding: 2px 6px; border-radius: 4px; }
        .tag-danger { background: rgba(239,68,68,0.15); color: #f87171; }
        .tag-warning { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .tag-info { background: rgba(56,189,248,0.15); color: #38bdf8; }
        .tag-success { background: rgba(34,197,94,0.15); color: #4ade80; }

        .card-title { font-size: 13px; font-weight: 600; color: #f1f5f9; line-height: 1.35; }
        .card-meta { font-size: 11px; color: #94a3b8; line-height: 1.4; }

        /* Preview Stage */
        .stage { flex: 1; background: #f1f5f9; display: flex; align-items: center; justify-content: center; padding: 24px; overflow: hidden; position: relative; }
        .frame-wrapper { height: 100%; width: 100%; max-width: 660px; transition: max-width 0.2s ease; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; background: #ffffff; }
        .frame-wrapper.tablet { max-width: 520px; }
        .frame-wrapper.mobile { max-width: 380px; }

        iframe { width: 100%; height: 100%; border: none; background: #ffffff; }

        .external-link-btn { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: #94a3b8; text-decoration: none; padding: 6px 10px; border-radius: 4px; background: #0f172a; border: 1px solid #334155; white-space: nowrap; }
        .external-link-btn:hover { color: #ffffff; border-color: #475569; }

        /* Toast feedback */
        .toast { position: fixed; bottom: 24px; right: 24px; background: #0f172a; border: 1px solid #334155; color: #ffffff; padding: 12px 18px; border-radius: 6px; font-size: 13px; font-weight: 500; box-shadow: 0 10px 25px rgba(0,0,0,0.3); transform: translateY(100px); opacity: 0; transition: all 0.25s ease; z-index: 999; }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.error { border-color: #f43f5e; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="logo">
            <span class="logo-badge">SMKI</span>
            <span class="logo-title">Galeri Notifikasi Email</span>
        </div>

        <div class="top-controls">
            <!-- Test Send To Real Inbox -->
            <form class="send-test-box" onsubmit="handleSendTest(event)">
                <input id="testEmailInput" type="email" class="send-test-input" placeholder="Masukkan email..." required value="{{ config('mail.always_to') }}">
                <button type="submit" id="sendBtn" class="send-test-btn">
                    <span>Kirim Test</span>
                </button>
            </form>

            <div class="device-toggle">
                <button type="button" class="device-btn active" onclick="setDevice('desktop', this)">Desktop</button>
                <button type="button" class="device-btn" onclick="setDevice('tablet', this)">Tablet</button>
                <button type="button" class="device-btn" onclick="setDevice('mobile', this)">Mobile</button>
            </div>

            <a id="openTabBtn" href="/email-preview/1-1-finding-created" target="_blank" class="external-link-btn">
                <span>Buka di Tab Baru</span> ↗
            </a>
        </div>
    </div>

    <div class="workspace">
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- Modul Temuan -->
            <div class="module-group">
                <div class="module-title">1. Modul Temuan (Findings)</div>
                
                <div class="template-card active" data-code="1-1" onclick="loadPreview('/email-preview/1-1-finding-created', '1-1', this)">
                    <div class="card-header">
                        <span class="card-tag tag-danger">1.1 MAYOR</span>
                        <span class="card-meta">Admin &rarr; PIC</span>
                    </div>
                    <div class="card-title">Temuan Baru Diterbitkan</div>
                    <div class="card-meta">Pemberitahuan temuan baru & batas SLA.</div>
                </div>

                <div class="template-card" data-code="1-2a" onclick="loadPreview('/email-preview/1-2-finding-pic-to-admin-update', '1-2a', this)">
                    <div class="card-header">
                        <span class="card-tag tag-info">1.2.a PROGRES</span>
                        <span class="card-meta">PIC &rarr; Admin</span>
                    </div>
                    <div class="card-title">PIC Update Progres / Selesai</div>
                    <div class="card-meta">PIC melaporkan status In Progress / Resolved.</div>
                </div>

                <div class="template-card" data-code="1-2b" onclick="loadPreview('/email-preview/1-2-finding-pic-to-admin-closed', '1-2b', this)">
                    <div class="card-header">
                        <span class="card-tag tag-success">1.2.b CLOSED</span>
                        <span class="card-meta">PIC &rarr; Admin</span>
                    </div>
                    <div class="card-title">PIC Menutup Temuan (Closed)</div>
                    <div class="card-meta">PIC menandai perbaikan unit selesai & ditutup.</div>
                </div>

                <div class="template-card" data-code="1-2c" onclick="loadPreview('/email-preview/1-2-finding-pic-to-admin-reverted', '1-2c', this)">
                    <div class="card-header">
                        <span class="card-tag tag-warning">1.2.c REVERT</span>
                        <span class="card-meta">PIC &rarr; Admin</span>
                    </div>
                    <div class="card-title">PIC Mengembalikan Status Temuan</div>
                    <div class="card-meta">PIC mengembalikan Resolved &rarr; In Progress.</div>
                </div>

                <div class="template-card" data-code="1-3a" onclick="loadPreview('/email-preview/1-3-finding-admin-to-pic-revision', '1-3a', this)">
                    <div class="card-header">
                        <span class="card-tag tag-warning">1.3.a REVISI</span>
                        <span class="card-meta">Admin &rarr; PIC</span>
                    </div>
                    <div class="card-title">Admin Mengembalikan (Perlu Revisi)</div>
                    <div class="card-meta">Bukti belum sesuai, dikembalikan ke PIC.</div>
                </div>

                <div class="template-card" data-code="1-3b" onclick="loadPreview('/email-preview/1-3-finding-admin-to-pic-closed', '1-3b', this)">
                    <div class="card-header">
                        <span class="card-tag tag-success">1.3.b CLOSED</span>
                        <span class="card-meta">Admin &rarr; PIC</span>
                    </div>
                    <div class="card-title">Admin Verifikasi & Tutup (Closed)</div>
                    <div class="card-meta">Admin memverifikasi bukti & resmi menutup temuan.</div>
                </div>

                <div class="template-card" data-code="1-3c" onclick="loadPreview('/email-preview/1-3-finding-admin-to-pic-progress', '1-3c', this)">
                    <div class="card-header">
                        <span class="card-tag tag-info">1.3.c PROGRES</span>
                        <span class="card-meta">Admin &rarr; PIC</span>
                    </div>
                    <div class="card-title">Admin Memperbarui Progres Temuan</div>
                    <div class="card-meta">Admin mengupdate status temuan.</div>
                </div>
            </div>

            <!-- Modul Checklist -->
            <div class="module-group">
                <div class="module-title">2. Modul Checklist Kepatuhan</div>
                
                <div class="template-card" data-code="2-1" onclick="loadPreview('/email-preview/2-1-checklist-rejected', '2-1', this)">
                    <div class="card-header">
                        <span class="card-tag tag-danger">2.1 TIDAK PATUH</span>
                        <span class="card-meta">Admin &rarr; PIC</span>
                    </div>
                    <div class="card-title">Checklist Ditolak / Tidak Patuh</div>
                    <div class="card-meta">Catatan penolakan bukti & langkah perbaikan.</div>
                </div>
            </div>

            <!-- Modul Keamanan Akun -->
            <div class="module-group">
                <div class="module-title">3. Keamanan Akun Pengguna</div>
                
                <div class="template-card" data-code="4-1" onclick="loadPreview('/email-preview/4-1-auth-reset-password', '4-1', this)">
                    <div class="card-header">
                        <span class="card-tag tag-info">3.1 SECURITY</span>
                        <span class="card-meta">Sistem &rarr; User</span>
                    </div>
                    <div class="card-title">Permintaan Reset Kata Sandi</div>
                    <div class="card-meta">Tautan aman penggantian password (token 60 menit).</div>
                </div>
            </div>
        </aside>

        <!-- Preview Stage Frame -->
        <main class="stage">
            <div id="frameWrapper" class="frame-wrapper">
                <iframe id="previewIframe" src="/email-preview/1-1-finding-created"></iframe>
            </div>
        </main>
    </div>

    <!-- Notification Toast -->
    <div id="toast" class="toast"></div>

    <script>
        let currentTemplateCode = '1-1';

        function loadPreview(url, code, el) {
            document.querySelectorAll('.template-card').forEach(c => c.classList.remove('active'));
            if (el) el.classList.add('active');
            currentTemplateCode = code;
            document.getElementById('previewIframe').src = url;
            document.getElementById('openTabBtn').href = url;
        }

        function setDevice(device, el) {
            document.querySelectorAll('.device-btn').forEach(b => b.classList.remove('active'));
            if (el) el.classList.add('active');
            const wrapper = document.getElementById('frameWrapper');
            wrapper.className = 'frame-wrapper ' + (device === 'desktop' ? '' : device);
        }

        function showToast(msg, isError = false) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.className = 'toast show ' + (isError ? 'error' : '');
            setTimeout(() => {
                toast.className = 'toast';
            }, 4000);
        }

        async function handleSendTest(e) {
            e.preventDefault();
            const email = document.getElementById('testEmailInput').value;
            const btn = document.getElementById('sendBtn');
            if (!email) return;

            btn.disabled = true;
            btn.innerHTML = '<span>Mengirim...</span>';

            try {
                const res = await fetch('/email-preview/send-test', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        email: email,
                        template: currentTemplateCode
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    showToast('Email test berhasil dikirim ke ' + email, false);
                } else {
                    showToast(data.message || 'Gagal mengirim email test.', true);
                }
            } catch (err) {
                showToast('Gagal menghubungi server.', true);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span>Kirim Test</span>';
            }
        }
    </script>
</body>
</html>
