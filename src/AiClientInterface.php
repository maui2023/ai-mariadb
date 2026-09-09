<?php
declare(strict_types=1);

namespace AiMariaDb;

interface AiClientInterface
{
    /**
     * Semak sama ada servis pembekal AI boleh dihubungi dan bersedia
     */
    public function isAvailable(): bool;

    /**
     * Jana vektor embedding daripada teks
     * 
     * @return float[] Array nombor float vektor
     */
    public function embed(string $text, ?string $model = null): array;

    /**
     * Hantar perbualan ke Chat LLM dengan arahan System Prompt
     */
    public function chat(string $systemPrompt, string $userMessage, ?string $model = null): string;

    /**
     * Dapatkan senarai model yang tersedia
     * 
     * @return string[]
     */
    public function listModels(): array;

    /**
     * Dapatkan nama pengecam pembekal ('ollama' atau 'gemini')
     */
    public function getProviderName(): string;
}
