<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use AiMariaDb\Database;

$method = $_SERVER['REQUEST_METHOD'];
$pdo = Database::getLocalPdo();

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT id, name, driver, db_host, db_port, db_name, db_user, selected_tables, status, last_synced_at, created_at FROM ai_db_connections ORDER BY id DESC");
        $connections = $stmt->fetchAll();

        foreach ($connections as &$conn) {
            $conn['selected_tables'] = json_decode($conn['selected_tables'] ?? '[]', true) ?: [];
            
            // Kira rekod vektor yang telah diindeks untuk sambungan ini
            $vStmt = $pdo->prepare("SELECT COUNT(*) FROM ai_knowledge_vectors WHERE connection_id = :id");
            $vStmt->execute(['id' => $conn['id']]);
            $conn['vectors_count'] = (int)$vStmt->fetchColumn();
        }

        echo json_encode(['success' => true, 'connections' => $connections]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $name = trim($input['name'] ?? 'Database Luar');
        $driver = strtolower($input['driver'] ?? 'mysql');
        $host = trim($input['host'] ?? '127.0.0.1');
        $port = (int)($input['port'] ?? 3306);
        $dbname = trim($input['database'] ?? '');
        $user = trim($input['username'] ?? '');
        $pass = (string)($input['password'] ?? '');
        $tables = $input['selected_tables'] ?? [];

        if (empty($dbname)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nama database diperlukan.']);
            exit;
        }

        // Tapis keluar sebarang jadual terlarang sebelum simpan
        $filteredTables = [];
        foreach ($tables as $tbl) {
            $tName = is_array($tbl) ? ($tbl['name'] ?? '') : (string)$tbl;
            if (!\AiMariaDb\TableInspector::isTableForbidden($tName)) {
                $filteredTables[] = $tName;
            }
        }

        $id = !empty($input['id']) ? (int)$input['id'] : null;

        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE ai_db_connections SET
                    name = :name,
                    driver = :driver,
                    db_host = :host,
                    db_port = :port,
                    db_name = :dbname,
                    db_user = :user,
                    db_pass = :pass,
                    selected_tables = :tables
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'driver' => $driver,
                'host' => $host,
                'port' => $port,
                'dbname' => $dbname,
                'user' => $user,
                'pass' => $pass,
                'tables' => json_encode($filteredTables),
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ai_db_connections (name, driver, db_host, db_port, db_name, db_user, db_pass, selected_tables)
                VALUES (:name, :driver, :host, :port, :dbname, :user, :pass, :tables)
            ");
            $stmt->execute([
                'name' => $name,
                'driver' => $driver,
                'host' => $host,
                'port' => $port,
                'dbname' => $dbname,
                'user' => $user,
                'pass' => $pass,
                'tables' => json_encode($filteredTables),
            ]);
            $id = (int)$pdo->lastInsertId();
        }

        echo json_encode([
            'success' => true,
            'id' => $id,
            'message' => 'Sambungan berjaya disimpan.',
            'selected_tables' => $filteredTables,
        ]);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
