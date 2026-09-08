<?php
declare(strict_types=1);

namespace AiMariaDb;

class Config
{
    // URL Pelayan Ollama
    public const OLLAMA_HOST = 'http://127.0.0.1:11434';

    // Model embedding & chat yang digunakan
    public const EMBEDDING_MODEL = 'embeddinggemma';
    public const CHAT_MODEL = 'qwen:0.5b-chat';

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

    // Direktori data tempatan untuk simpanan storan & tetapan
    public static function getDataDir(): string
    {
        $dir = dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
}
