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

use AiMariaDb\ChatService;

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$message = trim((string)($input['message'] ?? ''));

if ($message === '') {
    echo json_encode([
        'success' => false,
        'answer' => 'Sila ajukan soalan anda mengenai produk, servis atau waktu operasi.',
        'sources' => [],
    ]);
    exit;
}

try {
    $service = new ChatService();
    $result = $service->ask($message);

    echo json_encode([
        'success' => true,
        'answer' => $result['answer'],
        'sources' => $result['sources'] ?? [],
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'answer' => 'Maaf, sistem mengalami ralat teknikal buat seketika. Sila cuba lagi sebentar lagi.',
        'error' => $e->getMessage(),
    ]);
}
