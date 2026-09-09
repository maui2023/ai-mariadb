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
     * Dapatkan senarai model Gemini yang lazim disokong
     */
    public function listModels(): array
    {
        if (empty($this->apiKey)) {
            return [
                'gemini-2.5-flash',
                'gemini-flash-latest',
                'gemini-2.5-pro',
                'gemini-embedding-001',
            ];
        }

        try {
            $url = "{$this->baseUrl}/models?key=" . urlencode($this->apiKey);
            $res = $this->request($url, 'GET', null, 5);
            $models = [];
            if (!empty($res['models'])) {
                foreach ($res['models'] as $m) {
                    $name = str_replace('models/', '', $m['name'] ?? '');
                    if (!empty($name)) {
                        $models[] = $name;
                    }
                }
            }
            return !empty($models) ? $models : ['gemini-2.5-flash', 'gemini-flash-latest', 'gemini-embedding-001'];
        } catch (\Throwable $e) {
            return ['gemini-2.5-flash', 'gemini-flash-latest', 'gemini-2.5-pro', 'gemini-embedding-001'];
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
