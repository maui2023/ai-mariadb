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

use AiMariaDb\Config;
use AiMariaDb\GeminiClient;
use AiMariaDb\OllamaClient;

$method = $_SERVER['REQUEST_METHOD'];

function maskApiKey(string $key): string
{
    $key = trim($key);
    $len = strlen($key);
    if ($len <= 8) {
        return $len > 0 ? str_repeat('*', $len) : '';
    }
    return substr($key, 0, 6) . '...' . substr($key, -4);
}

try {
    if ($method === 'GET') {
        $settings = Config::getSettings();
        $ollama = new OllamaClient($settings['ollama_host'] ?? null);
        $gemini = new GeminiClient($settings['gemini_api_key'] ?? null);

        $ollamaOnline = $ollama->isAvailable();
        $geminiOnline = !empty($settings['gemini_api_key']) ? $gemini->isAvailable() : false;

        echo json_encode([
            'success' => true,
            'settings' => [
                'provider' => $settings['provider'] ?? 'ollama',
                'gemini_api_key_set' => !empty($settings['gemini_api_key']),
                'gemini_api_key_masked' => maskApiKey($settings['gemini_api_key'] ?? ''),
                'gemini_chat_model' => $settings['gemini_chat_model'] ?? Config::DEFAULT_GEMINI_CHAT_MODEL,
                'gemini_embedding_model' => $settings['gemini_embedding_model'] ?? Config::DEFAULT_GEMINI_EMBEDDING_MODEL,
                'ollama_host' => $settings['ollama_host'] ?? Config::OLLAMA_HOST,
                'ollama_chat_model' => $settings['ollama_chat_model'] ?? Config::CHAT_MODEL,
                'ollama_embedding_model' => $settings['ollama_embedding_model'] ?? Config::EMBEDDING_MODEL,
            ],
            'status' => [
                'ollama_online' => $ollamaOnline,
                'gemini_online' => $geminiOnline,
                'active_provider' => Config::getAiProvider(),
            ],
        ]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $action = $input['action'] ?? 'save';

        if ($action === 'test_gemini') {
            $apiKey = trim((string)($input['gemini_api_key'] ?? ''));
            if ($apiKey === '' || str_contains($apiKey, '...')) {
                $apiKey = Config::getGeminiApiKey();
            }

            if (empty($apiKey)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Sila masukkan Gemini API Key terlebih dahulu sebelum menguji sambungan.',
                ]);
                exit;
            }

            $client = new GeminiClient($apiKey);
            $available = $client->isAvailable();

            echo json_encode([
                'success' => $available,
                'message' => $available 
                    ? 'Sambungan Google Gemini BERJAYA! Kunci API sah.' 
                    : 'Gagal menyambung ke Google Gemini. Sila pastikan kunci API sah dan capaian internet tersedia.',
            ]);
            exit;
        }

        if ($action === 'test_ollama') {
            $host = trim((string)($input['ollama_host'] ?? Config::getOllamaHost()));
            $client = new OllamaClient($host);
            $available = $client->isAvailable();

            echo json_encode([
                'success' => $available,
                'message' => $available 
                    ? 'Sambungan Ollama BERJAYA! Servis beroperasi.' 
                    : "Gagal menyambung ke pelayan Ollama di {$host}. Pastikan perkhidmatan Ollama berjalan.",
            ]);
            exit;
        }

        // Action: save
        $current = Config::getSettings();
        $updates = [];

        if (isset($input['provider'])) {
            $prov = strtolower(trim((string)$input['provider']));
            if (in_array($prov, ['ollama', 'gemini'])) {
                $updates['provider'] = $prov;
            }
        }

        if (isset($input['gemini_api_key'])) {
            $newKey = trim((string)$input['gemini_api_key']);
            // Jangan timpa jika pengguna mengekalkan teks yang bertopengkan '...'
            if ($newKey !== '' && !str_contains($newKey, '...')) {
                $updates['gemini_api_key'] = $newKey;
            } elseif ($newKey === '') {
                $updates['gemini_api_key'] = '';
            }
        }

        if (!empty($input['gemini_chat_model'])) {
            $updates['gemini_chat_model'] = trim((string)$input['gemini_chat_model']);
        }
        if (!empty($input['gemini_embedding_model'])) {
            $updates['gemini_embedding_model'] = trim((string)$input['gemini_embedding_model']);
        }
        if (!empty($input['ollama_host'])) {
            $updates['ollama_host'] = trim((string)$input['ollama_host']);
        }
        if (!empty($input['ollama_chat_model'])) {
            $updates['ollama_chat_model'] = trim((string)$input['ollama_chat_model']);
        }
        if (!empty($input['ollama_embedding_model'])) {
            $updates['ollama_embedding_model'] = trim((string)$input['ollama_embedding_model']);
        }

        Config::saveSettings($updates);

        echo json_encode([
            'success' => true,
            'message' => 'Tetapan pembekal AI berjaya dikemaskini!',
            'settings' => [
                'provider' => Config::getAiProvider(),
                'gemini_api_key_set' => !empty(Config::getGeminiApiKey()),
                'gemini_api_key_masked' => maskApiKey(Config::getGeminiApiKey()),
                'gemini_chat_model' => Config::getGeminiChatModel(),
                'gemini_embedding_model' => Config::getGeminiEmbeddingModel(),
                'ollama_host' => Config::getOllamaHost(),
                'ollama_chat_model' => Config::getOllamaChatModel(),
                'ollama_embedding_model' => Config::getOllamaEmbeddingModel(),
            ],
        ]);
        exit;
    }

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
