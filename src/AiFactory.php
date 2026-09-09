<?php
declare(strict_types=1);

namespace AiMariaDb;

class AiFactory
{
    /**
     * Dapatkan pembekal AI aktif berdasarkan tetapan semasa
     */
    public static function getClient(?string $preferredProvider = null): AiClientInterface
    {
        $provider = strtolower($preferredProvider ?? Config::getAiProvider());

        if ($provider === 'gemini') {
            return self::getGeminiClient();
        }

        return self::getOllamaClient();
    }

    public static function getOllamaClient(): OllamaClient
    {
        return new OllamaClient(Config::getOllamaHost());
    }

    public static function getGeminiClient(): GeminiClient
    {
        return new GeminiClient(Config::getGeminiApiKey());
    }
}
