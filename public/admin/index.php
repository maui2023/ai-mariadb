<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use AiMariaDb\Config;
use AiMariaDb\Database;
use AiMariaDb\OllamaClient;

$ollama = new OllamaClient();
$ollamaOnline = $ollama->isAvailable();
$ollamaModels = $ollamaOnline ? $ollama->listModels() : [];

$pdo = Database::getLocalPdo();
$connCount = (int)$pdo->query("SELECT COUNT(*) FROM ai_db_connections")->fetchColumn();
$vectorCount = (int)$pdo->query("SELECT COUNT(*) FROM ai_knowledge_vectors")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ai-mariadb: Panel Pengurusan Chatbot Database</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0b0f19;
            --bg-card: #131b2e;
            --bg-input: #1a243d;
            --border-color: #233052;
            --accent-blue: #3b82f6;
            --accent-indigo: #6366f1;
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Navigation */
        header {
            background: rgba(19, 27, 46, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-indigo));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
        }

        .brand-title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.02em;
            background: linear-gradient(to right, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 6px;
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            font-weight: 600;
        }

        .server-status {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .status-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 600;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .dot-green { background: var(--accent-emerald); box-shadow: 0 0 10px var(--accent-emerald); }
        .dot-red { background: var(--accent-rose); box-shadow: 0 0 10px var(--accent-rose); }

        /* Container */
        .container {
            max-width: 1240px;
            width: 100%;
            margin: 0 auto;
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        /* Metric Grid */
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .metric-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.3);
        }

        .metric-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .metric-val {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
        }

        /* Main Grid */
        .main-layout {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 28px;
        }

        @media (max-width: 960px) {
            .main-layout {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 26px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            box-shadow: 0 15px 35px -10px rgba(0,0,0,0.4);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 16px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form elements */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        label {
            font-size: 13px;
            font-weight: 600;
            color: #cbd5e1;
        }

        input, select {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 14px;
            color: #ffffff;
            font-size: 13.5px;
            outline: none;
            transition: all 0.2s;
        }

        input:focus, select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .btn {
            border-radius: 10px;
            padding: 11px 18px;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-indigo));
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.35);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.45);
        }

        .btn-secondary {
            background: var(--bg-input);
            color: #cbd5e1;
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #233052;
            color: #ffffff;
        }

        /* Table List */
        .table-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 260px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .table-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-radius: 12px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            transition: all 0.15s;
        }

        .table-item.allowed:hover {
            border-color: rgba(59, 130, 246, 0.4);
            background: #202d4c;
        }

        .table-item.forbidden {
            background: rgba(244, 63, 94, 0.06);
            border-color: rgba(244, 63, 94, 0.25);
            opacity: 0.85;
        }

        .table-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .table-name {
            font-weight: 600;
            font-size: 13.5px;
        }

        .table-rows {
            font-size: 12px;
            color: var(--text-muted);
        }

        .badge {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badge-allowed {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-forbidden {
            background: rgba(244, 63, 94, 0.15);
            color: #fb7185;
            border: 1px solid rgba(244, 63, 94, 0.3);
        }

        /* Live Chat Test Window */
        .chat-test-box {
            display: flex;
            flex-direction: column;
            height: 380px;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
            background: #0f172a;
        }

        .chat-test-messages {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .msg-bubble {
            padding: 10px 14px;
            border-radius: 12px;
            max-width: 85%;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
        }

        .msg-user {
            align-self: flex-end;
            background: var(--accent-blue);
            color: #ffffff;
        }

        .msg-bot {
            align-self: flex-start;
            background: #1e293b;
            color: #e2e8f0;
            border: 1px solid #334155;
        }

        .chat-test-input-row {
            display: flex;
            padding: 12px;
            background: #131b2e;
            border-top: 1px solid var(--border-color);
            gap: 10px;
        }

        /* Embed Snippet Code Box */
        .code-box {
            background: #080c14;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px;
            font-family: monospace;
            font-size: 12px;
            color: #7dd3fc;
            overflow-x: auto;
            position: relative;
        }

        .copy-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: #cbd5e1;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
        }

        .copy-btn:hover { color: #ffffff; background: #233052; }

        /* Notification Toast */
        #toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 600;
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 1000;
        }

        #toast.show {
            transform: translateX(-50%) translateY(0);
        }
    </style>
</head>
<body>

    <header>
        <div class="brand">
            <div class="brand-icon">⚡</div>
            <div>
                <span class="brand-title">ai-mariadb</span>
                <span class="brand-badge">Plugin RAG v1.0</span>
            </div>
        </div>

        <div class="server-status">
            <div class="status-pill">
                <span class="dot <?= $ollamaOnline ? 'dot-green' : 'dot-red' ?>"></span>
                <span>Ollama: <?= $ollamaOnline ? 'Aktif' : 'Tidak Ditemui' ?></span>
            </div>
            <div class="status-pill">
                <span>Embedding: <strong><?= htmlspecialchars(Config::EMBEDDING_MODEL) ?></strong></span>
            </div>
            <div class="status-pill">
                <span>Chat LLM: <strong><?= htmlspecialchars(Config::CHAT_MODEL) ?></strong></span>
            </div>
            <a href="/demo_site.php" target="_blank" class="btn btn-secondary" style="padding: 6px 14px; font-size: 12.5px;">
                👁️ Buka Demo Web
            </a>
        </div>
    </header>

    <div class="container">
        
        <!-- Metric Cards -->
        <div class="metric-grid">
            <div class="metric-card">
                <div class="metric-label">Pangkalan Data Bersambung</div>
                <div class="metric-val" id="metric-conn"><?= $connCount ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Jumlah Vektor Pengetahuan (Chunks)</div>
                <div class="metric-val" id="metric-vec"><?= $vectorCount ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Status Perlindungan Data Privasi</div>
                <div class="metric-val" style="color: var(--accent-emerald); font-size: 20px;">🛡️ 100% Kebocoran Disekat</div>
            </div>
        </div>

        <div class="main-layout">
            
            <!-- Bahagian Kiri: Konfigurasi Database & Pilihan Jadual -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <span>🔌 Sambung Database Sistem Luar</span>
                    </div>
                    <span style="font-size: 12px; color: var(--text-muted);">Hanya Capaian Read-Only</span>
                </div>

                <form id="db-form" onsubmit="return false;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Rujukan Sistem</label>
                            <input type="text" id="db_name_label" value="Sistem POS Kedai" placeholder="cth: Kedai POS / WooCommerce">
                        </div>
                        <div class="form-group">
                            <label>Jenis Database</label>
                            <select id="db_driver" onchange="toggleDriverFields()">
                                <option value="mysql">MariaDB / MySQL</option>
                                <option value="sqlite">SQLite (Fail Tempatan / Ujian)</option>
                            </select>
                        </div>
                    </div>

                    <div id="mysql-fields" class="form-row" style="margin-top: 14px;">
                        <div class="form-group">
                            <label>Host & Port</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="db_host" value="127.0.0.1" style="flex: 2;">
                                <input type="number" id="db_port" value="3306" style="flex: 1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nama Database</label>
                            <input type="text" id="db_name" value="pos_inventory" placeholder="nama_pangkalan_data">
                        </div>
                    </div>

                    <div id="mysql-auth-fields" class="form-row" style="margin-top: 14px;">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" id="db_user" value="root">
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" id="db_pass" placeholder="••••••••">
                        </div>
                    </div>

                    <div id="sqlite-field" class="form-group" style="margin-top: 14px; display: none;">
                        <label>Laluan Fail SQLite</label>
                        <input type="text" id="db_sqlite_path" value="<?= htmlspecialchars(Config::getDataDir() . '/dummy_pos.sqlite') ?>" placeholder="/path/to/database.sqlite">
                    </div>

                    <div style="display: flex; gap: 12px; margin-top: 18px;">
                        <button type="button" class="btn btn-secondary" id="btn-load-tables" onclick="loadTables()">
                            🔍 Sambung & Muat Turun Jadual
                        </button>
                    </div>
                </form>

                <!-- Kawasan Pemilihan Jadual -->
                <div id="table-selection-box" style="display: none; border-top: 1px solid var(--border-color); padding-top: 18px; margin-top: 10px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <span style="font-size: 13.5px; font-weight: 700;">📋 Senarai Jadual Ditemui:</span>
                        <span style="font-size: 12px; color: var(--text-muted);">Tandakan jadual untuk diindeks</span>
                    </div>

                    <div class="table-list" id="tables-container">
                        <!-- Dimuat secara dinamik -->
                    </div>

                    <div style="margin-top: 18px; display: flex; gap: 12px;">
                        <button type="button" class="btn btn-primary" id="btn-save-sync" onclick="saveAndSync()">
                            🚀 Simpan & Jana Vektor AI (embeddinggemma)
                        </button>
                    </div>

                    <div id="sync-progress-log" style="display: none; margin-top: 14px; background: #0a0e17; border: 1px solid var(--border-color); border-radius: 10px; padding: 12px; font-size: 12px; color: #a5b4fc; max-height: 120px; overflow-y: auto;">
                    </div>
                </div>

            </div>

            <!-- Bahagian Kanan: Live Chat Tester & Embed Snippet -->
            <div style="display: flex; flex-direction: column; gap: 28px;">
                
                <!-- Chatbot Live Tester -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <span>💬 Uji Chatbot AI Secara Langsung</span>
                        </div>
                        <span class="badge badge-allowed">Live Preview</span>
                    </div>

                    <div class="chat-test-box">
                        <div class="chat-test-messages" id="admin-chat-logs">
                            <div class="msg-bubble msg-bot">Hai! Saya pembantu AI yang membaca rekod database anda. Sila ajukan sebarang soalan contoh mengenai produk, stok, atau waktu operasi.</div>
                        </div>
                        <div class="chat-test-input-row">
                            <input type="text" id="admin-chat-input" placeholder="Tanya soalan contoh..." style="flex: 1;" onkeydown="if(event.key==='Enter') sendAdminChat()">
                            <button class="btn btn-primary" style="padding: 8px 14px;" onclick="sendAdminChat()">Hantar</button>
                        </div>
                    </div>
                </div>

                <!-- Embed Snippet Box -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <span>🔗 Cara Pasang ke Website (Embed)</span>
                        </div>
                    </div>
                    
                    <p style="font-size: 13px; color: var(--text-muted);">Salin kod di bawah dan letakkan sebelum penutup <code>&lt;/body&gt;</code> pada mana-mana website anda:</p>

                    <div class="code-box">
                        <button class="copy-btn" onclick="copySnippet()">Salin</button>
                        <code id="embed-code-text">&lt;!-- AiMariaDb Chatbot Widget --&gt;
&lt;script 
    src="http://<?= $_SERVER['HTTP_HOST'] ?? 'localhost:8080' ?>/widget/chat.js" 
    data-api="http://<?= $_SERVER['HTTP_HOST'] ?? 'localhost:8080' ?>/api/chat.php"
    data-title="Pembantu Kedai AI"
    async defer&gt;
&lt;/script&gt;</code>
                    </div>

                    <p style="font-size: 12px; color: #64748b;">Untuk framework PHP (Laravel/WordPress): Anda juga boleh menggunakan <code>require_once 'plugin/embed.php'; render_ai_chatbot();</code></p>
                </div>

            </div>

        </div>

    </div>

    <div id="toast"></div>

    <script>
        function toggleDriverFields() {
            const driver = document.getElementById('db_driver').value;
            const mysqlFields = document.getElementById('mysql-fields');
            const mysqlAuth = document.getElementById('mysql-auth-fields');
            const sqliteField = document.getElementById('sqlite-field');

            if (driver === 'sqlite') {
                mysqlFields.style.display = 'none';
                mysqlAuth.style.display = 'none';
                sqliteField.style.display = 'block';
            } else {
                mysqlFields.style.display = 'grid';
                mysqlAuth.style.display = 'grid';
                sqliteField.style.display = 'none';
            }
        }

        let inspectedTables = [];
        let currentSavedConnectionId = null;

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3500);
        }

        async function loadTables() {
            const btn = document.getElementById('btn-load-tables');
            btn.textContent = 'Menyambung...';
            btn.disabled = true;

            const driver = document.getElementById('db_driver').value;
            const payload = {
                driver: driver,
                host: document.getElementById('db_host').value,
                port: document.getElementById('db_port').value,
                database: driver === 'sqlite' ? document.getElementById('db_sqlite_path').value : document.getElementById('db_name').value,
                username: document.getElementById('db_user').value,
                password: document.getElementById('db_pass').value,
            };

            try {
                const res = await fetch('/api/tables.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                btn.textContent = '🔍 Sambung & Muat Turun Jadual';
                btn.disabled = false;

                if (!data.success) {
                    showToast('Ralat: ' + (data.error || 'Gagal memuat turun jadual.'));
                    return;
                }

                inspectedTables = data.tables || [];
                renderTableList(inspectedTables);
                document.getElementById('table-selection-box').style.display = 'block';
                showToast(`Berjaya memuat turun ${inspectedTables.length} jadual.`);
            } catch (e) {
                btn.textContent = '🔍 Sambung & Muat Turun Jadual';
                btn.disabled = false;
                showToast('Ralat sambungan: ' + e.message);
            }
        }

        function renderTableList(tables) {
            const container = document.getElementById('tables-container');
            container.innerHTML = '';

            tables.forEach(t => {
                const div = document.createElement('div');
                div.className = `table-item ${t.is_forbidden ? 'forbidden' : 'allowed'}`;

                if (t.is_forbidden) {
                    div.innerHTML = `
                        <div class="table-item-left">
                            <input type="checkbox" disabled style="cursor: not-allowed;">
                            <div>
                                <div class="table-name" style="color: #f87171;">🔒 ${t.name}</div>
                                <div class="table-rows" style="color: #fda4af;">${t.forbidden_reason}</div>
                            </div>
                        </div>
                        <span class="badge badge-forbidden">DISEKAT TEGAR</span>
                    `;
                } else {
                    // Pra-pilih jadual selamat secara automatik
                    div.innerHTML = `
                        <div class="table-item-left">
                            <input type="checkbox" class="tbl-check" value="${t.name}" checked id="tbl_${t.name}">
                            <label for="tbl_${t.name}" style="cursor: pointer;">
                                <div class="table-name">${t.name}</div>
                                <div class="table-rows">${t.row_count} rekod data</div>
                            </label>
                        </div>
                        <span class="badge badge-allowed">DIBENARKAN</span>
                    `;
                }
                container.appendChild(div);
            });
        }

        async function saveAndSync() {
            const btn = document.getElementById('btn-save-sync');
            const progressBox = document.getElementById('sync-progress-log');
            btn.textContent = 'Menjana Vektor AI...';
            btn.disabled = true;
            progressBox.style.display = 'block';
            progressBox.innerHTML = '<div>Mula menyimpan sambungan dan mengindeks dengan embeddinggemma...</div>';

            // Dapatkan jadual yang ditandakan
            const checkedBoxes = document.querySelectorAll('.tbl-check:checked');
            const selectedTables = Array.from(checkedBoxes).map(cb => cb.value);

            if (selectedTables.length === 0) {
                showToast('Sila tandakan sekurang-kurangnya satu jadual.');
                btn.textContent = '🚀 Simpan & Jana Vektor AI (embeddinggemma)';
                btn.disabled = false;
                return;
            }

            const driver = document.getElementById('db_driver').value;
            const connPayload = {
                name: document.getElementById('db_name_label').value,
                driver: driver,
                host: document.getElementById('db_host').value,
                port: document.getElementById('db_port').value,
                database: driver === 'sqlite' ? document.getElementById('db_sqlite_path').value : document.getElementById('db_name').value,
                username: document.getElementById('db_user').value,
                password: document.getElementById('db_pass').value,
                selected_tables: selectedTables,
            };

            try {
                // 1. Simpan sambungan
                const connRes = await fetch('/api/connections.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(connPayload),
                });
                const connData = await connRes.json();

                if (!connData.success) {
                    throw new Error(connData.error || 'Gagal menyimpan sambungan.');
                }

                currentSavedConnectionId = connData.id;
                progressBox.innerHTML += `<div>Sambungan disimpan (ID: ${currentSavedConnectionId}). Memulakan indexing...</div>`;

                // 2. Jana indeks & embedding
                const syncRes = await fetch('/api/sync.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ connection_id: currentSavedConnectionId }),
                });
                const syncData = await syncRes.json();

                if (!syncData.success) {
                    throw new Error(syncData.error || 'Gagal mengindeks vektor.');
                }

                if (syncData.logs) {
                    syncData.logs.forEach(l => {
                        progressBox.innerHTML += `<div>✓ ${l}</div>`;
                    });
                }
                progressBox.innerHTML += `<div style="color: #34d399; font-weight: bold; margin-top: 4px;">🎉 Selesai! ${syncData.message}</div>`;
                showToast('Pengindeksan AI berjaya!');

                // Kemaskini metric
                document.getElementById('metric-conn').textContent = parseInt(document.getElementById('metric-conn').textContent) + 1;
                document.getElementById('metric-vec').textContent = parseInt(document.getElementById('metric-vec').textContent) + (syncData.details?.total_indexed || 0);

            } catch (err) {
                progressBox.innerHTML += `<div style="color: #fb7185;">RALAT: ${err.message}</div>`;
                showToast('Ralat: ' + err.message);
            } finally {
                btn.textContent = '🚀 Simpan & Jana Vektor AI (embeddinggemma)';
                btn.disabled = false;
            }
        }

        async function sendAdminChat() {
            const input = document.getElementById('admin-chat-input');
            const q = input.value.trim();
            if (!q) return;

            input.value = '';
            const logs = document.getElementById('admin-chat-logs');

            const userMsg = document.createElement('div');
            userMsg.className = 'msg-bubble msg-user';
            userMsg.textContent = q;
            logs.appendChild(userMsg);

            const botMsg = document.createElement('div');
            botMsg.className = 'msg-bubble msg-bot';
            botMsg.textContent = 'Sedang mencari maklumat dalam database...';
            logs.appendChild(botMsg);
            logs.scrollTop = logs.scrollHeight;

            try {
                const res = await fetch('/api/chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: q }),
                });
                const data = await res.json();
                botMsg.textContent = data.answer || 'Tiada maklumat.';
            } catch (e) {
                botMsg.textContent = 'Ralat menghubungi API chatbot.';
            }
            logs.scrollTop = logs.scrollHeight;
        }

        function copySnippet() {
            const code = document.getElementById('embed-code-text').textContent;
            navigator.clipboard.writeText(code).then(() => {
                showToast('Kod sematan berjaya disalin!');
            });
        }

        // Jalankan permulaan secara automatik dengan memuat turun dummy database sekiranya ada
        window.addEventListener('DOMContentLoaded', () => {
            // Pilih SQLite secara lalai untuk memudahkan demonstrasi segera
            document.getElementById('db_driver').value = 'sqlite';
            toggleDriverFields();
            loadTables();
        });
    </script>
</body>
</html>
