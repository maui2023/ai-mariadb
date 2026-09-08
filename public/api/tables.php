<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use AiMariaDb\Database;
use AiMariaDb\TableInspector;

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (empty($input['database'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nama database diperlukan.']);
    exit;
}

try {
    $driver = strtolower($input['driver'] ?? 'mysql');
    $pdo = Database::connectExternal([
        'driver' => $driver,
        'host' => $input['host'] ?? '127.0.0.1',
        'port' => (int)($input['port'] ?? 3306),
        'database' => $input['database'],
        'username' => $input['username'] ?? '',
        'password' => $input['password'] ?? '',
    ]);

    $tables = TableInspector::inspectDatabase($pdo, $driver, $input['database']);

    echo json_encode([
        'success' => true,
        'driver' => $driver,
        'database' => $input['database'],
        'tables' => $tables,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Gagal menyambung ke database: ' . $e->getMessage(),
    ]);
}
