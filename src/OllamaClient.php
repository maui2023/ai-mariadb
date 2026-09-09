<?php
declare(strict_types=1);

namespace AiMariaDb;

use RuntimeException;

class OllamaClient implements AiClientInterface
{
    private string $host;

    public function __construct(?string $host = null)
    {
        $this->host = rtrim($host ?? Config::OLLAMA_HOST, '/');
    }

    public function getProviderName(): string
    {
        return 'ollama';
    }

    /**
     * Semak sama ada servis Ollama sedang beroperasi
     */
    public function isAvailable(): bool
    {
        $ch = curl_init("{$this->host}/api/tags");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $httpCode === 200 && $res !== false;
    }

    /**
     * Senarai model yang dipasang pada Ollama
     */
    public function listModels(): array
    {
        $response = $this->request('/api/tags', 'GET');
        $models = [];
        if (!empty($response['models'])) {
            foreach ($response['models'] as $m) {
                $models[] = $m['name'] ?? '';
            }
        }
        return array_filter($models);
    }

    /**
     * Jana vektor embedding daripada teks menggunakan model embedding (cth: embeddinggemma)
     * @return float[] Array nombor float vektor
     */
    public function embed(string $text, ?string $model = null): array
    {
        $model = $model ?? Config::EMBEDDING_MODEL;

        $payload = [
            'model' => $model,
            'input' => $text,
        ];

        $response = $this->request('/api/embed', 'POST', $payload, 90);

        // Semak format output Ollama (/api/embed memulangkan 'embeddings' array)
        if (!empty($response['embeddings'][0]) && is_array($response['embeddings'][0])) {
            return $response['embeddings'][0];
        }

        // Sesetengah versi menggunakan endpoint /api/embeddings dengan respons 'embedding'
        if (!empty($response['embedding']) && is_array($response['embedding'])) {
            return $response['embedding'];
        }

        throw new RuntimeException("Gagal menjana embedding dari model '{$model}'. Respons tidak sah.");
    }

    /**
     * Hantar perbualan ke Chat LLM dengan arahan System Prompt yang ketat
     */
    public function chat(string $systemPrompt, string $userMessage, ?string $model = null): string
    {
        $model = $model ?? Config::CHAT_MODEL;

        $payload = [
            'model' => $model,
            'stream' => false,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userMessage,
                ],
            ],
            'options' => [
                'temperature' => 0.0, // Sifar rawak untuk jawapan 100% berpandukan fakta
                'num_predict' => 120, // Ruang mencukupi untuk ayat lengkap
            ],
        ];

        $response = $this->request('/api/chat', 'POST', $payload, 120);

        if (!empty($response['message']['content'])) {
            return trim($response['message']['content']);
        }

        throw new RuntimeException("Model chat '{$model}' tidak memberikan jawapan.");
    }

    /**
     * Bantuan panggilan HTTP cURL ke endpoint Ollama
     */
    private function request(string $endpoint, string $method = 'GET', ?array $data = null, int $timeout = 60): array
    {
        $url = $this->host . $endpoint;
        $ch = curl_init($url);

        $headers = ['Content-Type: application/json'];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
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
            throw new RuntimeException("Sambungan ke pelayan Ollama gagal: " . $err);
        }

        if ($code >= 400) {
            throw new RuntimeException("Ollama mengembalikan kod ralat HTTP {$code}: " . $raw);
        }

        $json = json_decode($raw, true);
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Gagal menghuraikan JSON daripada Ollama: " . $raw);
        }

        return $json;
    }
}
