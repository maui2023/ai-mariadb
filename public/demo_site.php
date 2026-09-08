<?php
declare(strict_types=1);

// Contoh laman web kedai e-dagang / perniagaan PHP
require_once dirname(__DIR__) . '/plugin/embed.php';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Butik & Kedai Moden - Contoh Laman Web Pelanggan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --bg-page: #f8fafc;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-page);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* Navbar */
        nav {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .nav-logo {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links {
            display: flex;
            gap: 24px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .nav-links a {
            text-decoration: none;
            color: inherit;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .admin-link-btn {
            background: #f1f5f9;
            color: #334155;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .admin-link-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Hero */
        .hero {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
            text-align: center;
        }

        .hero h1 {
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
            margin-bottom: 16px;
        }

        .hero p {
            font-size: 17px;
            color: var(--text-muted);
            max-width: 650px;
            margin: 0 auto 28px;
            line-height: 1.6;
        }

        /* Product Grid */
        .section-title {
            max-width: 1100px;
            margin: 40px auto 20px;
            padding: 0 20px;
            font-size: 22px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .product-grid {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .product-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
        }

        .product-badge {
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 11.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            width: fit-content;
        }

        .product-title {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
        }

        .product-desc {
            font-size: 13.5px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .product-meta {
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }

        .product-price {
            font-size: 18px;
            font-weight: 800;
            color: var(--primary);
        }

        .product-stock {
            font-size: 12.5px;
            color: #059669;
            font-weight: 600;
        }

        /* Store Hours Card */
        .hours-box {
            max-width: 1100px;
            margin: 40px auto 60px;
            padding: 24px 28px;
            background: #eff6ff;
            border-radius: 16px;
            border: 1px solid #bfdbfe;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .hours-info h3 {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #1e3a8a;
        }

        .hours-info p {
            font-size: 14px;
            color: #3b82f6;
        }

        /* Callout Box */
        .notice-banner {
            max-width: 1100px;
            margin: 20px auto;
            padding: 16px 20px;
            background: #fefce8;
            border: 1px solid #fef08a;
            border-radius: 12px;
            font-size: 13.5px;
            color: #854d0e;
            display: flex;
            align-items: center;
            gap: 12px;
        }
    </style>
</head>
<body>

    <nav>
        <div class="nav-logo">
            🛍️ Butik Moden
        </div>
        <div class="nav-links">
            <a href="#katalog">Katalog</a>
            <a href="#waktu">Waktu Kedai</a>
            <a href="#bantuan">Bantuan</a>
        </div>
        <div>
            <a href="/admin/index.php" class="admin-link-btn">⚙️ Buka Panel Admin AI</a>
        </div>
    </nav>

    <div class="notice-banner">
        <span>💡</span>
        <span><strong>Laman Demo:</strong> Chatbot di sudut bawah kanan membaca maklumat pangkalan data kedai ini secara langsung melalui model <strong>embeddinggemma</strong>! Cuba klik gelembung sembang di bawah.</span>
    </div>

    <section class="hero">
        <h1>Koleksi Fesyen & Kasut Terkini</h1>
        <p>Beli-belah dengan mudah. Anda boleh bertanyakan status stok, saiz, dan waktu operasi kedai kepada Pembantu Pintar AI kami di sudut bawah kanan.</p>
    </section>

    <div class="section-title" id="katalog">
        <span>Katalog Terpilih</span>
        <span style="font-size: 13px; font-weight: 500; color: #64748b;">Data diekstrak terus dari database MariaDB / SQLite</span>
    </div>

    <div class="product-grid">
        <div class="product-card">
            <span class="product-badge">Pakaian Tradisional</span>
            <div class="product-title">Baju Melayu Moden Hitam</div>
            <div class="product-desc">Baju melayu potongan moden kain cotton selesa saiz S, M, L, XL. Sesuai untuk acara rasmi dan hari raya.</div>
            <div class="product-meta">
                <span class="product-price">RM 120.00</span>
                <span class="product-stock">Tersedia: 15 unit</span>
            </div>
        </div>

        <div class="product-card">
            <span class="product-badge">Kasut & Sukan</span>
            <div class="product-title">Kasut Larian Nimbus 42</div>
            <div class="product-desc">Kasut sukan larian kusyen tebal saiz 42 warna biru. Ringan dan melindungi lutut ketika berjoging.</div>
            <div class="product-meta">
                <span class="product-price">RM 280.00</span>
                <span class="product-stock">Tersedia: 4 unit (Stok Terhad)</span>
            </div>
        </div>

        <div class="product-card">
            <span class="product-badge">Aksesori</span>
            <div class="product-title">Kopiah Putih Klasik</div>
            <div class="product-desc">Kopiah rajut berkualiti tinggi, kemas dan selesa dipakai sepanjang hari.</div>
            <div class="product-meta">
                <span class="product-price">RM 25.00</span>
                <span class="product-stock">Tersedia: 50 unit</span>
            </div>
        </div>
    </div>

    <div class="hours-box" id="waktu">
        <div class="hours-info">
            <h3>🕒 Waktu Operasi Cawangan</h3>
            <p>Isnin - Jumaat (9:00 AM - 9:00 PM) | Sabtu (10:00 AM - 10:00 PM) | Ahad (10:00 AM - 6:00 PM)</p>
        </div>
        <div>
            <button onclick="document.getElementById('ai-chat-bubble')?.click()" style="padding: 10px 18px; border-radius: 10px; background: var(--primary); color: #fff; border: none; font-weight: 700; cursor: pointer;">
                Tanya Chatbot AI
            </button>
        </div>
    </div>

    <!-- 
      SEMATAN MUDAH CHATBOT WIDGET
      Menggunakan fungsi pembantu PHP render_ai_chatbot() daripada plugin/embed.php 
    -->
    <?= render_ai_chatbot([
        'api_url' => '/api/chat.php',
        'widget_url' => '/widget/chat.js',
        'title' => 'Pembantu Butik AI',
        'greeting' => 'Hai! 👋 Selamat datang ke Butik Moden. Anda boleh tanya saya tentang stok saiz kasut, baju melayu atau waktu kedai kami!',
    ]) ?>

</body>
</html>
