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

use AiMariaDb\Indexer;

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$connectionId = (int)($input['connection_id'] ?? 0);

if ($connectionId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Connection ID tidak sah.']);
    exit;
}

try {
    $indexer = new Indexer();
    $logs = [];

    $result = $indexer->indexConnection($connectionId, function(string $msg) use (&$logs) {
        $logs[] = $msg;
    });

    echo json_encode([
        'success' => true,
        'message' => "Berjaya mengindeks {$result['total_indexed']} rekod.",
        'details' => $result,
        'logs' => $logs,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Proses indeks gagal: ' . $e->getMessage(),
    ]);
}
