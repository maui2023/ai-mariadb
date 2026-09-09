<?php
declare(strict_types=1);

namespace AiMariaDb;

use Throwable;

class ChatService
{
    private AiClientInterface $ai;
    private ?OllamaClient $fallbackOllama = null;

    public function __construct(?AiClientInterface $ai = null)
    {
        $this->ai = $ai ?? AiFactory::getClient();
        $this->fallbackOllama = AiFactory::getOllamaClient();
    }

    /**
     * Semak sama ada ralat berpunca daripada kuota API tamat atau had token dicapai
     */
    private function isTokenOrQuotaExhausted(Throwable $e): bool
    {
        if ($e->getCode() === 429) {
            return true;
        }

        $msg = strtolower($e->getMessage());
        $keywords = [
            'quota',
            'resource_exhausted',
            'rate limit',
            'rate_limit',
            'token',
            'exhausted',
            'too many requests',
            'insufficient_quota',
            'credit',
        ];

        foreach ($keywords as $kw) {
            if (str_contains($msg, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Proses soalan daripada pelanggan dan jana jawapan berasaskan database & konteks perbualan
     * 
     * @param string $question Soalan terkini pelanggan
     * @param array $history Sejarah perbualan terdahulu: [['role' => 'user'|'assistant', 'content' => '...']]
     */
    public function ask(string $question, array $history = []): array
    {
        $cleanQuestion = trim($question);
        if (empty($cleanQuestion)) {
            return [
                'answer' => 'Sila masukkan soalan anda.',
                'sources' => [],
            ];
        }

        // Bina teks konteks carian berasaskan soalan semasa dan perbualan lepas
        $contextualSearchText = $cleanQuestion;
        $historyLines = [];
        if (!empty($history)) {
            $recentHistory = array_slice($history, -4);
            $historySnippet = [];
            foreach ($recentHistory as $msg) {
                $role = ($msg['role'] ?? '') === 'user' ? 'Pelanggan' : 'Pembantu';
                $content = trim((string)($msg['content'] ?? ''));
                if ($content !== '') {
                    $historyLines[] = "{$role}: {$content}";
                    $historySnippet[] = $content;
                }
            }
            if (!empty($historySnippet)) {
                // Sertakan konteks dari perbualan terdahulu untuk memastikan carian vektor memahami entiti yang dirujuk
                $contextualSearchText = $cleanQuestion . ' ' . implode(' ', $historySnippet);
            }
        }

        try {
            // 1. Tukar soalan kepada vektor nombor (menggunakan carian konteks perbualan)
            try {
                $questionVector = $this->ai->embed($contextualSearchText);
            } catch (Throwable $embedEx) {
                if ($this->ai->getProviderName() === 'gemini') {
                    if ($this->fallbackOllama !== null && $this->fallbackOllama->isAvailable()) {
                        $questionVector = $this->fallbackOllama->embed($contextualSearchText);
                    } else {
                        throw $embedEx;
                    }
                } else {
                    throw $embedEx;
                }
            }

            // 2. Cari rekod terdekat dalam pangkalan data pengetahuan
            $relevantRecords = VectorSearch::search($questionVector, limit: 8, threshold: 0.25);

            // 3. Sekiranya tiada rekod yang sepadan
            if (empty($relevantRecords)) {
                return [
                    'answer' => "Maaf, maklumat berkenaan soalan anda tidak ditemui dalam rekod database kami buat masa ini. Sila hubungi khidmat staf kami untuk bantuan lanjut.",
                    'sources' => [],
                ];
            }

            // Pilih dan kurasi rekod secara pintar mengikut keperluan soalan
            $topScore = $relevantRecords[0]['score'];
            $filteredRecords = [];
            $productCount = 0;

            // Semak jika soalan atau perbualan lepas menyentuh promosi atau diskaun
            $isPromoQuery = (bool)preg_match('/\b(diskaun|diskuan|promosi|promo|jualan|potongan|voucher|kupon|percuma|free|tawaran)\b/i', $contextualSearchText);

            foreach ($relevantRecords as $rec) {
                if ($rec['source_table'] === 'products') {
                    // Simpan produk utama paling relevan (maksimum 2 jika skor hampir sama)
                    if ($productCount === 0) {
                        $filteredRecords[] = $rec;
                        $productCount++;
                    } elseif ($productCount < 2 && $rec['score'] >= ($topScore - 0.06)) {
                        $filteredRecords[] = $rec;
                        $productCount++;
                    }
                } else {
                    // Jadual sokongan (promotions, store_policies, store_hours)
                    if ($rec['source_table'] === 'promotions') {
                        if ($isPromoQuery || $rec['score'] >= 0.35 || $rec['score'] >= ($topScore * 0.65)) {
                            $filteredRecords[] = $rec;
                        }
                    } else {
                        if ($rec['score'] >= 0.40 || $rec['score'] >= ($topScore * 0.70)) {
                            $filteredRecords[] = $rec;
                        }
                    }
                }
            }

            if (empty($filteredRecords)) {
                $filteredRecords = array_slice($relevantRecords, 0, 4);
            }

            // 4. Susun konteks berstruktur bersih daripada rekod yang dijumpai
            $contextText = "";
            $sources = [];

            foreach ($filteredRecords as $rec) {
                // Bersihkan tag teknikal dan formatkan kepada ayat perniagaan semulajadi
                $text = preg_replace('/^\[Sumber:\s*[^\]]+\]\s*/i', '', $rec['content']);
                $text = preg_replace('/\bid:\s*\d+\s*\|\s*/i', '', $text); // Buang id: 1 dsb.
                $text = preg_replace('/\bprice:\s*(\d+(?:\.\d+)?)/i', 'Harga: RM $1', $text);
                $text = preg_replace('/\bstock:\s*(\d+)/i', 'Stok: $1 unit', $text);
                $text = preg_replace('/\bname:\s*/i', '', $text);
                $text = preg_replace('/\bdescription:\s*/i', 'Penerangan: ', $text);
                $text = str_replace(' | ', ', ', $text);
                $contextText .= "- " . trim($text) . "\n";
                $sources[] = [
                    'table' => $rec['source_table'],
                    'title' => $rec['title'],
                    'score' => $rec['score'],
                ];
            }

            // 5. Arahan System Prompt Ringkas & Fleksibel Bersama Sejarah Perbualan
            $historyBlock = "";
            if (!empty($historyLines)) {
                $historyBlock = "\n[SEJARAH PERBUALAN LEPAS]\n" . implode("\n", $historyLines) . "\n";
            }

            $systemPrompt = <<<PROMPT
Anda ialah pembantu AI khidmat pelanggan rasmi bagi perniagaan ini.
Tugas anda ialah menjawab soalan pelanggan dengan TEPAT, RINGKAS (1-2 ayat sahaja) dan PADAT berpandukan [MAKLUMAT PERNIAGAAN] dan [SEJARAH PERBUALAN LEPAS].

ARAHAN:
1. Jawab soalan secara terus berdasarkan fakta yang diberikan.
2. Gunakan mata wang Ringgit Malaysia (RM) sahaja.
3. Fahami konteks soalan susulan pelanggan berdasarkan [SEJARAH PERBUALAN LEPAS] (contoh: jika pelanggan bertanya soalan pendek seperti "selepas diskaun", "jika ada diskaun berapa harga selepas itu", "ada stok lagi?", atau "ada warna apa", fahami dengan tepat bahawa mereka sedang merujuk kepada item/produk yang baru dibincangkan dalam perbualan lepas).
4. Semak sama ada item tersebut layak mendapat diskaun berdasarkan syarat promosi dalam [MAKLUMAT PERNIAGAAN]:
   - Jika item layak promosi diskaun, kira dan nyatakan harga akhir selepas diskaun dalam RM.
   - Jika item tidak termasuk dalam promosi atau tiada diskaun bagi kategorinya (contoh: promosi hanya untuk kasut/pakaian manakala item ialah aksesori/elektronik), jelaskan dengan sopan bahawa tiada promosi diskaun untuk kategori/item tersebut dan harganya kekal pada harga asal.
5. JANGAN mereka maklumat atau membuat andaian di luar maklumat yang dibekalkan.
6. JANGAN menyebut istilah teknikal seperti "products", "store_hours", "database", atau nama jadual.
7. Sekiranya maklumat berkenaan soalan tiada dalam fakta di bawah, jawab HANYA:
   "Maaf, maklumat berkenaan soalan anda tidak ditemui dalam rekod database kami buat masa ini. Sila hubungi khidmat staf kami untuk bantuan lanjut."

[MAKLUMAT PERNIAGAAN]
{$contextText}
{$historyBlock}
PROMPT;

            // 6. Dapatkan jawapan daripada model Chat LLM, dengan fallback in-memory ke Ollama jika Gemini gagal/kehabisan kuota
            try {
                $answer = $this->ai->chat($systemPrompt, $cleanQuestion);
            } catch (Throwable $chatEx) {
                // Sekiranya Gemini gagal, kehabisan token, 429, atau 503, cuba Ollama serta-merta
                if ($this->ai->getProviderName() === 'gemini') {
                    if ($this->fallbackOllama !== null && $this->fallbackOllama->isAvailable()) {
                        try {
                            $answer = $this->fallbackOllama->chat($systemPrompt, $cleanQuestion);
                        } catch (Throwable $ollamaEx) {
                            // Formatkan data terus daripada rekod database jika kedua-dua servis tergendala
                            $answer = "Berdasarkan maklumat perniagaan kami:\n";
                            foreach ($filteredRecords as $rec) {
                                $cleanContent = preg_replace('/^\[Sumber:\s*[^\]]+\]\s*/i', '', $rec['content']);
                                $answer .= "• " . $cleanContent . "\n";
                            }
                        }
                    } else {
                        // Fallback pintar rekod
                        $answer = "Berdasarkan maklumat perniagaan kami:\n";
                        foreach ($filteredRecords as $rec) {
                            $cleanContent = preg_replace('/^\[Sumber:\s*[^\]]+\]\s*/i', '', $rec['content']);
                            $answer .= "• " . $cleanContent . "\n";
                        }
                    }
                } else {
                    // Fallback pintar: Formatkan data terus daripada rekod database jika LLM tergendala
                    $answer = "Berdasarkan maklumat perniagaan kami:\n";
                    foreach ($filteredRecords as $rec) {
                        $cleanContent = preg_replace('/^\[Sumber:\s*[^\]]+\]\s*/i', '', $rec['content']);
                        $answer .= "• " . $cleanContent . "\n";
                    }
                }
            }

            $cleanAnswer = trim($answer);

            // Sanitasi Keselamatan Tambahan: Hapuskan sebarang cubaan LLM memetik nama jadual teknikal
            $forbiddenTechnicalPatterns = [
                '/\b(jadual|table|laman|website|sumber)\s+["\']?(products|store_hours|inventory_items|categories)["\']?/i',
                '/\b(products|store_hours)\b/i',
            ];
            foreach ($forbiddenTechnicalPatterns as $pattern) {
                if (preg_match($pattern, $cleanAnswer)) {
                    // Jika LLM masih membocorkan nama jadual secara tidak wajar, gantikan dengan istilah mesra pengguna
                    $cleanAnswer = preg_replace('/\bproducts\b/i', 'katalog produk', $cleanAnswer);
                    $cleanAnswer = preg_replace('/\bstore_hours\b/i', 'waktu operasi kedai', $cleanAnswer);
                }
            }

            return [
                'answer' => $cleanAnswer,
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
