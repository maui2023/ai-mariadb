<?php
declare(strict_types=1);

namespace AiMariaDb;

class Config
{
    // URL Pelayan Ollama
    public const OLLAMA_HOST = 'http://localhost:11434';

    // Model embedding & chat yang digunakan
    public const EMBEDDING_MODEL = 'embeddinggemma';
    public const CHAT_MODEL = 'qwen2.5:0.5b';

    // Senarai jadual sensitif yang DIHARAMKAN sama sekali daripada diindeks/diakses
    public const STRICT_FORBIDDEN_KEYWORDS = [
        'user',
        'users',
        'account',
        'accounts',
        'admin',
        'admins',
        'password',
        'passwords',
        'password_reset',
        'password_resets',
        'token',
        'tokens',
        'personal_access_tokens',
        'session',
        'sessions',
        'failed_jobs',
        'migration',
        'migrations',
        'credit_card',
        'credit_cards',
        'payment_credential',
        'oauth',
        'secret',
        'secrets',
    ];

    // Kolum sensitif yang disaring keluar sekiranya ada pada mana-mana jadual
    public const STRICT_FORBIDDEN_COLUMNS = [
        'password',
        'passwd',
        'pass',
        'secret',
        'token',
        'auth_token',
        'remember_token',
        'credit_card',
        'cvv',
        'api_key',
        'pin',
        'ssn',
        'nric',
    ];

    // Tetapan lalai Gemini
    public const DEFAULT_GEMINI_CHAT_MODEL = 'gemini-2.5-flash';
    public const DEFAULT_GEMINI_EMBEDDING_MODEL = 'gemini-embedding-001';

    // Direktori data tempatan untuk simpanan storan & tetapan
    public static function getDataDir(): string
    {
        $dir = dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Dapatkan semua tetapan AI (membaca fail data/ai_settings.json & env)
     */
    public static function getSettings(): array
    {
        $file = self::getDataDir() . '/ai_settings.json';
        $saved = [];
        if (file_exists($file)) {
            $saved = json_decode(file_get_contents($file) ?: '{}', true) ?: [];
        }

        $envKey = getenv('GEMINI_API_KEY') ?: '';
        $envProvider = getenv('AI_PROVIDER') ?: '';

        return [
            'provider' => $saved['provider'] ?? ($envProvider !== '' ? $envProvider : 'gemini'),
            'gemini_api_key' => $saved['gemini_api_key'] ?? $envKey,
            'gemini_chat_model' => $saved['gemini_chat_model'] ?? self::DEFAULT_GEMINI_CHAT_MODEL,
            'gemini_embedding_model' => $saved['gemini_embedding_model'] ?? self::DEFAULT_GEMINI_EMBEDDING_MODEL,
            'ollama_host' => $saved['ollama_host'] ?? self::OLLAMA_HOST,
            'ollama_chat_model' => $saved['ollama_chat_model'] ?? self::CHAT_MODEL,
            'ollama_embedding_model' => $saved['ollama_embedding_model'] ?? self::EMBEDDING_MODEL,
        ];
    }

    /**
     * Simpan tetapan AI ke dalam fail data/ai_settings.json
     */
    public static function saveSettings(array $newSettings): bool
    {
        $current = self::getSettings();
        $merged = array_merge($current, $newSettings);

        $file = self::getDataDir() . '/ai_settings.json';
        $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents($file, $json) !== false;
    }

    public static function getAiProvider(): string
    {
        $settings = self::getSettings();
        return strtolower($settings['provider'] ?? 'ollama');
    }

    public static function getGeminiApiKey(): string
    {
        $settings = self::getSettings();
        return (string)($settings['gemini_api_key'] ?? '');
    }

    public static function getGeminiChatModel(): string
    {
        $settings = self::getSettings();
        return (string)($settings['gemini_chat_model'] ?? self::DEFAULT_GEMINI_CHAT_MODEL);
    }

    public static function getGeminiEmbeddingModel(): string
    {
        $settings = self::getSettings();
        return (string)($settings['gemini_embedding_model'] ?? self::DEFAULT_GEMINI_EMBEDDING_MODEL);
    }

    public static function getOllamaHost(): string
    {
        $settings = self::getSettings();
        return (string)($settings['ollama_host'] ?? self::OLLAMA_HOST);
    }

    public static function getOllamaChatModel(): string
    {
        $settings = self::getSettings();
        return (string)($settings['ollama_chat_model'] ?? self::CHAT_MODEL);
    }

    public static function getOllamaEmbeddingModel(): string
    {
        $settings = self::getSettings();
        return (string)($settings['ollama_embedding_model'] ?? self::EMBEDDING_MODEL);
    }
}
