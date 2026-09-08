<?php
declare(strict_types=1);

namespace AiMariaDb;

use Throwable;

class ChatService
{
    private OllamaClient $ollama;

    public function __construct(?OllamaClient $ollama = null)
    {
        $this->ollama = $ollama ?? new OllamaClient();
    }

    /**
     * Proses soalan daripada pelanggan dan jana jawapan berasaskan database
     */
    public function ask(string $question): array
    {
        $cleanQuestion = trim($question);
        if (empty($cleanQuestion)) {
            return [
                'answer' => 'Sila masukkan soalan anda.',
                'sources' => [],
            ];
        }

        try {
            // 1. Tukar soalan kepada vektor nombor
            $questionVector = $this->ollama->embed($cleanQuestion);

            // 2. Cari rekod terdekat dalam pangkalan data pengetahuan
            $relevantRecords = VectorSearch::search($questionVector, limit: 4, threshold: 0.25);

            // 3. Sekiranya tiada rekod yang sepadan
            if (empty($relevantRecords)) {
                return [
                    'answer' => "Maaf, maklumat berkenaan soalan anda tidak ditemui dalam rekod database kami buat masa ini. Sila hubungi khidmat staf kami untuk bantuan lanjut.",
                    'sources' => [],
                ];
            }

            // 4. Susun konteks berstruktur daripada rekod yang dijumpai
            $contextText = "";
            $sources = [];

            foreach ($relevantRecords as $rec) {
                $contextText .= "- " . $rec['content'] . "\n";
                $sources[] = [
                    'table' => $rec['source_table'],
                    'title' => $rec['title'],
                    'score' => $rec['score'],
                ];
            }

            // 5. Arahan System Prompt Ketat (Strict Context Boundary)
            $systemPrompt = <<<PROMPT
Anda ialah pembantu AI khidmat pelanggan rasmi.

ARAHAN KETAT:
1. Jawab soalan HANYA berpandukan fakta di dalam [KONTEKS DATABASE] di bawah.
2. Berikan jawapan terus dan padat (1 ayat sahaja). Contoh: Jika ditanya warna, nyatakan warna sahaja mengikut rekod. Jika ditanya stok atau waktu, berikan maklumat tersebut secara tepat.
3. DILARANG SAMA SEKALI mereka jawapan, membuat andaian, atau bercakap di luar maklumat yang dibekalkan.
4. Sekiranya maklumat tiada dalam konteks, jawab: "Maaf, maklumat tersebut tiada dalam rekod kami."

[KONTEKS DATABASE]
{$contextText}
PROMPT;

            // 6. Dapatkan jawapan daripada model Chat LLM, dengan fallback pintar
            try {
                $answer = $this->ollama->chat($systemPrompt, $cleanQuestion);
            } catch (Throwable $chatEx) {
                // Fallback pintar: Formatkan data terus daripada rekod database jika LLM tergendala
                $answer = "Berdasarkan rekod database kami:\n";
                foreach ($relevantRecords as $rec) {
                    $answer .= "• " . $rec['content'] . "\n";
                }
            }

            return [
                'answer' => trim($answer),
                'sources' => $sources,
            ];

        } catch (Throwable $e) {
            return [
                'answer' => "Maaf, terdapat gangguan teknikal semasa memproses permohonan anda: " . $e->getMessage(),
                'sources' => [],
            ];
        }
    }
}
