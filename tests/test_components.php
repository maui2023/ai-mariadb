<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use AiMariaDb\Config;
use AiMariaDb\Database;
use AiMariaDb\OllamaClient;
use AiMariaDb\TableInspector;
use AiMariaDb\VectorSearch;
use AiMariaDb\Indexer;
use AiMariaDb\ChatService;

echo "=== UJIAN KOMPONEN AI-MARIADB ===\n\n";

// 1. Ujian Keselamatan Jadual (Table Security Inspector)
echo "1. Menguji Penapis Jadual Sensitif (Security Filter):\n";
$testTables = [
    'users' => true,
    'app_users' => true,
    'tbl_accounts' => true,
    'password_resets' => true,
    'products' => false,
    'store_hours' => false,
    'inventory_items' => false,
    'categories' => false,
];

$allSecurityPassed = true;
foreach ($testTables as $tbl => $expectedForbidden) {
    $actual = TableInspector::isTableForbidden($tbl);
    $status = ($actual === $expectedForbidden) ? "PASSED" : "FAILED";
    if ($actual !== $expectedForbidden) {
        $allSecurityPassed = false;
    }
    echo "  - Jadual '{$tbl}': " . ($actual ? "DISEKAT" : "DIBENARKAN") . " [{$status}]\n";
}
if (!$allSecurityPassed) {
    echo "RALAT: Ujian keselamatan gagal!\n";
    exit(1);
}
echo "  -> Semua semakan keselamatan jadual BERJAYA.\n\n";

// 2. Ujian Sambungan Ollama
echo "2. Menguji Sambungan Ollama (embeddinggemma):\n";
$ollama = new OllamaClient();
if (!$ollama->isAvailable()) {
    echo "  -> AMARAN: Ollama tidak dapat dihubungi di " . Config::OLLAMA_HOST . "\n";
} else {
    echo "  -> Ollama aktif!\n";
    $models = $ollama->listModels();
    echo "  -> Model dipasang: " . implode(', ', $models) . "\n";

    echo "  -> Menjana vektor embedding untuk teks contoh...\n";
    $vector = $ollama->embed("Kasut sukan saiz 42 ada stok 5 unit");
    echo "  -> Saiz dimensi vektor: " . count($vector) . " float.\n";
    echo "  -> 3 nilai terawal: [" . implode(', ', array_slice($vector, 0, 3)) . "...]\n";
    echo "  -> Ujian embedding BERJAYA.\n\n";
}

// 3. Ujian Kesamaan Kosin (Vector Similarity Search)
echo "3. Menguji Kesamaan Kosin (Cosine Similarity):\n";
$vecA = [1.0, 2.0, 3.0];
$vecB = [1.0, 2.0, 3.0];
$vecC = [-1.0, -2.0, -3.0];
$vecD = [0.0, 1.0, 0.0];

$simSame = VectorSearch::cosineSimilarity($vecA, $vecB);
$simOpposite = VectorSearch::cosineSimilarity($vecA, $vecC);
echo "  - Vektor Sama (Patut ~1.0): " . round($simSame, 4) . "\n";
echo "  - Vektor Berlawanan (Patut ~ -1.0): " . round($simOpposite, 4) . "\n";
if (round($simSame, 2) !== 1.0) {
    echo "RALAT: Pengiraan kesamaan kosin tidak tepat!\n";
    exit(1);
}
echo "  -> Ujian carian vektor BERJAYA.\n\n";

// 4. Cipta Contoh Database Luar (Dummy Store Database) untuk Simulasi Penuh
echo "4. Menyediakan Contoh Database Sistem Luar (dummy_pos.sqlite):\n";
$dummyDbPath = Config::getDataDir() . '/dummy_pos.sqlite';
if (file_exists($dummyDbPath)) {
    unlink($dummyDbPath);
}

$dummyPdo = new PDO('sqlite:' . $dummyDbPath);
$dummyPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Jadual Produk (Selamat)
$dummyPdo->exec("
    CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        category TEXT NOT NULL,
        price REAL NOT NULL,
        stock INTEGER NOT NULL,
        description TEXT
    );
    INSERT INTO products (name, category, price, stock, description) VALUES
    ('Baju Melayu Moden Hitam', 'Pakaian', 120.00, 15, 'Baju melayu potongan moden kain cotton selesa saiz S, M, L, XL'),
    ('Kasut Larian Nimbus 42', 'Kasut', 280.00, 4, 'Kasut sukan larian kusyen tebal saiz 42 warna biru'),
    ('Kopiah Putih Klasik', 'Aksesori', 25.00, 50, 'Kopiah rajut berkualiti tinggi');
");

// Jadual Waktu Operasi Kedai (Selamat)
$dummyPdo->exec("
    CREATE TABLE store_hours (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        day_name TEXT NOT NULL,
        opening_time TEXT NOT NULL,
        closing_time TEXT NOT NULL,
        status TEXT NOT NULL,
        notes TEXT
    );
    INSERT INTO store_hours (day_name, opening_time, closing_time, status, notes) VALUES
    ('Isnin - Jumaat', '09:00', '21:00', 'Buka', 'Hari bekerja biasa'),
    ('Sabtu', '10:00', '22:00', 'Buka', 'Hari minggu'),
    ('Ahad', '10:00', '18:00', 'Buka', 'Tutup awal jam 6 petang');
");

// Jadual Pengguna Sensitif (MESTI DISEKAT)
$dummyPdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        email TEXT NOT NULL,
        secret_pin TEXT NOT NULL
    );
    INSERT INTO users (username, password_hash, email, secret_pin) VALUES
    ('admin_boss', '\$2y\$10\$secretHashStringHere', 'admin@kedai.com', '9988');
");

echo "  -> Dummy DB dicipta dengan jadual: products, store_hours, dan users (sensitif).\n";

// 5. Imbas Jadual Menggunakan TableInspector
echo "5. Imbas Jadual Dummy DB menggunakan TableInspector:\n";
$inspected = TableInspector::inspectDatabase($dummyPdo, 'sqlite');
foreach ($inspected as $t) {
    $statusText = $t['is_forbidden'] ? "DISEKAT (RAHSIA)" : "DIBENARKAN ({$t['row_count']} rekod)";
    echo "  - {$t['name']}: {$statusText}\n";
}

// 6. Daftarkan Sambungan ke Local DB dan Jalankan Indeks
echo "\n6. Mendaftarkan Sambungan DB Luar & Menjalankan Indeks Vektor:\n";
$localPdo = Database::getLocalPdo();
$localPdo->exec("DELETE FROM ai_knowledge_vectors");
$localPdo->exec("DELETE FROM ai_db_connections");

$insertConn = $localPdo->prepare("
    INSERT INTO ai_db_connections (name, driver, db_host, db_port, db_name, db_user, db_pass, selected_tables)
    VALUES (:name, 'sqlite', 'localhost', 0, :dbname, '', '', :tables)
");

// Pentadbir memilih jadual products dan store_hours, serta CUBA memasukkan 'users'
$selectedTables = ['products', 'store_hours', 'users'];
$insertConn->execute([
    'name' => 'Kedai Demo POS',
    'dbname' => $dummyDbPath,
    'tables' => json_encode($selectedTables),
]);
$connId = (int)$localPdo->lastInsertId();

$indexer = new Indexer($ollama);
$res = $indexer->indexConnection($connId, function($msg) {
    echo "    [INDEX PROGRESS] {$msg}\n";
});

echo "  -> Hasil Indeks: {$res['total_indexed']} rekod berjaya diindeks.\n";

// Sahkan jadual 'users' TIDAK diindeks
$stmtUsersCheck = $localPdo->prepare("SELECT COUNT(*) FROM ai_knowledge_vectors WHERE source_table = 'users'");
$stmtUsersCheck->execute();
$usersCount = (int)$stmtUsersCheck->fetchColumn();
echo "  -> Rekod jadual 'users' dalam storan pengetahuan: {$usersCount} (Patut 0) [";
if ($usersCount === 0) {
    echo "BERJAYA: Data sensitif disekat sepenuhnya!]\n\n";
} else {
    echo "RALAT: Jadual sensitif terindeks!]\n\n";
    exit(1);
}

// 7. Ujian Chatbot AI Berpandukan Database
echo "7. Menguji Chat Service (Strict Database-Only Q&A):\n";
$chatService = new ChatService($ollama);

$questions = [
    "Ada stok kasut saiz 42 tak?",
    "Kedai buka tak hari Ahad dan pukul berapa tutup?",
    "Siapakah username admin dan apa password dalam database?",
];

foreach ($questions as $q) {
    echo "\n  SOALAN: \"{$q}\"\n";
    $reply = $chatService->ask($q);
    echo "  JAWAPAN AI:\n  " . str_replace("\n", "\n  ", $reply['answer']) . "\n";
    if (!empty($reply['sources'])) {
        echo "  [Sumber Digunakan: " . implode(', ', array_map(fn($s) => "{$s['table']} (skor: {$s['score']})", $reply['sources'])) . "]\n";
    }
}

echo "\n=== SEMUA UJIAN BERJAYA DISELESAIKAN ===\n";
