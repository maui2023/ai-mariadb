<?php
declare(strict_types=1);

namespace AiMariaDb;

use RuntimeException;

class GeminiClient implements AiClientInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = trim($apiKey ?? Config::getGeminiApiKey());
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    /**
     * Semak sama ada Google Gemini API boleh dihubungi dan kunci API sah
     */
    public function isAvailable(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        try {
            $url = "{$this->baseUrl}/models?key=" . urlencode($this->apiKey);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            return $code === 200 && $res !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Dapatkan senarai model yang disokong terus daripada Google Gemini API
     * dan tapis kepada Model Chat & Model Embedding yang sesuai untuk dipilih pengguna
     */
    public function fetchAvailableModels(?string $apiKey = null): array
    {
        $key = trim($apiKey ?? $this->apiKey);
        if (empty($key)) {
            return self::getFallbackModels();
        }

        try {
            $url = "{$this->baseUrl}/models?key=" . urlencode($key);
            $res = $this->request($url, 'GET', null, 8);

            if (empty($res['models']) || !is_array($res['models'])) {
                return self::getFallbackModels();
            }

            $chatModels = [];
            $embeddingModels = [];

            $excludedChatKeywords = [
                'tts', 'transcribe', 'image', 'preview-image', 'vision-only',
                'veo', 'banana', 'lyria', 'robotics', 'clip', 'customtools',
                'computer-use', 'bidi', 'live', 'native-audio', 'aqa'
            ];

            foreach ($res['models'] as $m) {
                $fullName = $m['name'] ?? '';
                $modelId = str_replace('models/', '', $fullName);
                $displayName = $m['displayName'] ?? $modelId;
                $description = $m['description'] ?? '';
                $methods = $m['supportedGenerationMethods'] ?? [];

                // 1. Tapis Model Embedding
                if (in_array('embedContent', $methods, true) || in_array('batchEmbedContents', $methods, true) || str_contains($modelId, 'embedding')) {
                    $embeddingModels[] = [
                        'id' => $modelId,
                        'name' => $displayName ?: $modelId,
                        'description' => $description,
                    ];
                    continue;
                }

                // 2. Tapis Model Chat / Teks Generasi
                if (in_array('generateContent', $methods, true)) {
                    $skip = false;
                    foreach ($excludedChatKeywords as $k) {
                        if (str_contains(strtolower($modelId), $k)) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) {
                        continue;
                    }

                    $chatModels[] = [
                        'id' => $modelId,
                        'name' => $displayName ?: $modelId,
                        'description' => $description,
                    ];
                }
            }

            // Susun model chat supaya model disyorkan (flash & pro) berada di atas
            usort($chatModels, function ($a, $b) {
                $scoreA = 0;
                $scoreB = 0;
                $idA = strtolower($a['id']);
                $idB = strtolower($b['id']);

                if (str_contains($idA, '3.6-flash')) $scoreA += 110;
                if (str_contains($idA, 'flash-latest')) $scoreA += 105;
                if (str_contains($idA, '3.5-flash')) $scoreA += 100;
                if (str_contains($idA, '2.5-flash')) $scoreA += 90;
                if (str_contains($idA, 'flash')) $scoreA += 50;
                if (str_contains($idA, 'pro')) $scoreA += 40;

                if (str_contains($idB, '3.6-flash')) $scoreB += 110;
                if (str_contains($idB, 'flash-latest')) $scoreB += 105;
                if (str_contains($idB, '3.5-flash')) $scoreB += 100;
                if (str_contains($idB, '2.5-flash')) $scoreB += 90;
                if (str_contains($idB, 'flash')) $scoreB += 50;
                if (str_contains($idB, 'pro')) $scoreB += 40;

                return $scoreB <=> $scoreA;
            });

            // Susun model embedding
            usort($embeddingModels, function ($a, $b) {
                $idA = strtolower($a['id']);
                $idB = strtolower($b['id']);
                $scoreA = str_contains($idA, 'embedding-001') ? 20 : (str_contains($idA, 'text-embedding') ? 15 : 10);
                $scoreB = str_contains($idB, 'embedding-001') ? 20 : (str_contains($idB, 'text-embedding') ? 15 : 10);
                return $scoreB <=> $scoreA;
            });

            return [
                'chat_models' => !empty($chatModels) ? $chatModels : self::getFallbackModels()['chat_models'],
                'embedding_models' => !empty($embeddingModels) ? $embeddingModels : self::getFallbackModels()['embedding_models'],
                'total_count' => count($chatModels) + count($embeddingModels),
            ];

        } catch (\Throwable $e) {
            // Lemparkan pengecualian jika mesej ralat khusus daripada Google (cth: kunci tidak sah / bocor)
            throw $e;
        }
    }

    /**
     * Senarai model sandaran standard Google Gemini
     */
    public static function getFallbackModels(): array
    {
        return [
            'chat_models' => [
                ['id' => 'gemini-3.6-flash', 'name' => 'Gemini 3.6 Flash (Terkini & Disyorkan)', 'description' => 'Pantas dan disyorkan untuk chatbot butik'],
                ['id' => 'gemini-flash-latest', 'name' => 'Gemini Flash Latest', 'description' => 'Model versi flash terkini'],
                ['id' => 'gemini-3.5-flash', 'name' => 'Gemini 3.5 Flash', 'description' => 'Versi flash stabil'],
                ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash', 'description' => 'Model pantas dan cekap'],
                ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro', 'description' => 'Penaakulan mendalam dan kompleks'],
                ['id' => 'gemini-3.1-flash-lite', 'name' => 'Gemini 3.1 Flash Lite', 'description' => 'Sangat ringan & pantas'],
            ],
            'embedding_models' => [
                ['id' => 'gemini-embedding-001', 'name' => 'gemini-embedding-001 (768d - Disyorkan)', 'description' => 'Vektor 768-dimensi (Piawaian ai-mariadb)'],
                ['id' => 'gemini-embedding-2', 'name' => 'gemini-embedding-2', 'description' => 'Vektor generasi baharu'],
                ['id' => 'text-embedding-004', 'name' => 'text-embedding-004', 'description' => 'Model embedding Google v4'],
            ],
            'total_count' => 9,
        ];
    }

    /**
     * Dapatkan senarai ID model Gemini untuk kegunaan umum
     */
    public function listModels(): array
    {
        try {
            $data = $this->fetchAvailableModels();
            $ids = [];
            foreach ($data['chat_models'] as $m) {
                $ids[] = $m['id'];
            }
            foreach ($data['embedding_models'] as $m) {
                $ids[] = $m['id'];
            }
            return !empty($ids) ? $ids : ['gemini-3.6-flash', 'gemini-flash-latest', 'gemini-embedding-001'];
        } catch (\Throwable $e) {
            return ['gemini-3.6-flash', 'gemini-flash-latest', 'gemini-2.5-pro', 'gemini-embedding-001'];
        }
    }

    /**
     * Jana vektor embedding daripada teks menggunakan model Google Gemini
     * 
     * @return float[] Array nombor float vektor 768 dimensi
     */
    public function embed(string $text, ?string $model = null): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException("Kunci API Gemini (GEMINI_API_KEY) belum ditetapkan. Sila masukkan kunci API dalam tetapan.");
        }

        $model = $model ?? Config::getGeminiEmbeddingModel();
        $modelClean = str_replace('models/', '', $model);

        $url = "{$this->baseUrl}/models/{$modelClean}:embedContent?key=" . urlencode($this->apiKey);

        $payload = [
            'model' => "models/{$modelClean}",
            'content' => [
                'parts' => [
                    ['text' => $text]
                ]
            ],
            'outputDimensionality' => 768,
        ];

        $response = $this->request($url, 'POST', $payload, 30);

        if (!empty($response['embedding']['values']) && is_array($response['embedding']['values'])) {
            return $response['embedding']['values'];
        }

        throw new RuntimeException("Gagal menjana embedding dari Gemini ({$modelClean}). Respons tidak sah atau tiada nilai vektor.");
    }

    /**
     * Hantar perbualan ke model Gemini Chat LLM dengan arahan System Prompt
     */
    public function chat(string $systemPrompt, string $userMessage, ?string $model = null): string
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException("Kunci API Gemini (GEMINI_API_KEY) belum ditetapkan. Sila masukkan kunci API dalam tetapan.");
        }

        $model = $model ?? Config::getGeminiChatModel();
        $modelClean = str_replace('models/', '', $model);

        $url = "{$this->baseUrl}/models/{$modelClean}:generateContent?key=" . urlencode($this->apiKey);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userMessage]
                    ]
                ]
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.0,
                'maxOutputTokens' => 1024,
            ],
        ];

        $response = $this->request($url, 'POST', $payload, 45);

        if (!empty($response['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($response['candidates'][0]['content']['parts'][0]['text']);
        }

        if (!empty($response['candidates'][0]['finishReason'])) {
            $reason = $response['candidates'][0]['finishReason'];
            if ($reason !== 'STOP') {
                throw new RuntimeException("Jawapan Gemini dihentikan atas sebab: {$reason}");
            }
        }

        throw new RuntimeException("Model Gemini ({$modelClean}) tidak memulangkan sebarang teks.");
    }

    /**
     * Bantuan cURL HTTP request ke Gemini API
     */
    private function request(string $url, string $method = 'GET', ?array $data = null, int $timeout = 30): array
    {
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = curl_init($url);

            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
            ];

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
                }
            }

            $raw = curl_exec($ch);
            $err = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($raw === false) {
                if ($attempt < $maxAttempts) {
                    usleep(500000); // 0.5s
                    continue;
                }
                throw new RuntimeException("Sambungan ke pelayan Gemini gagal: " . $err);
            }

            $json = json_decode($raw, true);

            if ($code >= 400) {
                // Cuba sekali lagi jika ralat 503 (high demand) atau 429 (rate limit sementara)
                if (($code === 503 || $code === 429) && $attempt < $maxAttempts) {
                    sleep(1);
                    continue;
                }
                $errMsg = $json['error']['message'] ?? "Kod ralat HTTP {$code}";
                throw new RuntimeException("Ralat Google Gemini API ({$code}): {$errMsg}", $code);
            }

            if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException("Gagal menghuraikan JSON daripada Gemini: " . $raw);
            }

            return $json;
        }

        throw new RuntimeException("Ralat memproses permohonan ke Google Gemini.");
    }
}
