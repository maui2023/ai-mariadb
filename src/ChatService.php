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
            $questionVector = null;
            try {
                $questionVector = $this->ai->embed($contextualSearchText);
            } catch (Throwable $embedEx) {
                if ($this->ai->getProviderName() === 'gemini') {
                    if ($this->fallbackOllama !== null && $this->fallbackOllama->isAvailable()) {
                        try {
                            $questionVector = $this->fallbackOllama->embed($contextualSearchText);
                        } catch (Throwable $oEx) {
                            // Servis embed Ollama tergendala juga, terus ke fallback kata kunci
                        }
                    }
                }
            }

            // 2. Cari rekod terdekat dalam pangkalan data pengetahuan
            if ($questionVector !== null) {
                $relevantRecords = VectorSearch::search($questionVector, limit: 8, threshold: 0.25);
            } else {
                // Fallback carian kata kunci pintar jika servis embedding AI tidak dapat diakses
                $relevantRecords = VectorSearch::searchByKeyword($contextualSearchText, limit: 8);
            }

            // 3. Sekiranya tiada rekod yang sepadan
            if (empty($relevantRecords)) {
                return [
                    'answer' => "Maaf, maklumat berkenaan pertanyaan anda tidak ditemui dalam rekod perniagaan kami buat masa ini. Sila hubungi staf kami untuk bantuan lanjut.",
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
                    // Simpan produk utama jika ia benar-benar relevan dengan soalan pengguna
                    $isRelevantProduct = ($rec['score'] >= ($topScore * 0.70) && $rec['score'] >= 0.30);
                    if ($isRelevantProduct && $productCount < 2) {
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

            // 4. Susun konteks berstruktur bersih daripada rekod yang dijumpai tanpa ID teknikal
            $contextText = "";
            $sources = [];

            foreach ($filteredRecords as $rec) {
                // Bersihkan tag teknikal dan formatkan kepada ayat perniagaan semulajadi
                $text = preg_replace('/^\[Sumber:\s*[^\]]+\]\s*/i', '', $rec['content']);
                $text = preg_replace('/\bid:\s*\d+(\s*\|\s*)?/i', '', $text); // Buang id: 1 dsb.
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

            // 5. Arahan System Prompt Khidmat Pelanggan Mesra & Semulajadi
            $historyBlock = "";
            if (!empty($historyLines)) {
                $historyBlock = "\n[SEJARAH PERBUALAN LEPAS]\n" . implode("\n", $historyLines) . "\n";
            }

            $systemPrompt = <<<PROMPT
Anda ialah pembantu AI khidmat pelanggan rasmi bagi perniagaan ini.
Tugas anda ialah melayani soalan pelanggan dengan ramah, mesra, sopan dan profesional berpandukan [MAKLUMAT PERNIAGAAN] dan [SEJARAH PERBUALAN LEPAS].

ARAHAN PENTING:
1. Berikan jawapan seperti seorang pembantu khidmat pelanggan manusia yang berbudi bahasa dan mesra.
2. JANGAN SEKALI-KALI memaparkan ID pangkalan data (contoh: "id: 1" atau seumpamanya), nama kolum mentah pangkalan data, atau sintaks pemisah paip ("|").
3. Sampaikan jawapan dalam ayat perbualan yang lengkap, jelas dan mudah difahami pelanggan.
4. Gunakan mata wang Ringgit Malaysia (RM) sahaja.
5. Fahami konteks soalan susulan pelanggan berdasarkan [SEJARAH PERBUALAN LEPAS] (contoh: jika pelanggan bertanya "selepas diskaun berapa?", "ada stok lagi?", atau "warna apa", fahami dengan tepat produk yang sedang dibincangkan).
6. Semak sama ada item tersebut layak mendapat promosi atau diskaun dalam [MAKLUMAT PERNIAGAAN]:
   - Jika item layak promosi diskaun, kira dan nyatakan harga akhir selepas diskaun dalam RM.
   - Jika item tidak termasuk dalam promosi atau tiada diskaun bagi kategorinya, jelaskan dengan sopan bahawa tawaran itu tidak terpakai untuk item berkenaan dan harganya kekal pada harga asal.
7. JANGAN mereka maklumat atau membuat sebarang andaian di luar maklumat yang dibekalkan.
8. JANGAN menyebut istilah teknikal seperti "products", "store_hours", "database", atau nama jadual sistem.
9. Sekiranya maklumat berkenaan soalan tiada dalam fakta di bawah, jawab dengan sopan:
   "Maaf, maklumat berkenaan pertanyaan anda tidak ditemui dalam rekod perniagaan kami buat masa ini. Sila hubungi khidmat staf kami untuk bantuan lanjut."

[MAKLUMAT PERNIAGAAN]
{$contextText}
{$historyBlock}
PROMPT;

            // 6. Dapatkan jawapan daripada model Chat LLM, dengan fallback perbualan pintar jika servis AI tergendala
            try {
                $answer = $this->ai->chat($systemPrompt, $cleanQuestion);
            } catch (Throwable $chatEx) {
                // Sekiranya Gemini gagal, kehabisan token, 429, atau 503, cuba Ollama serta-merta
                if ($this->ai->getProviderName() === 'gemini' && $this->fallbackOllama !== null && $this->fallbackOllama->isAvailable()) {
                    try {
                        $answer = $this->fallbackOllama->chat($systemPrompt, $cleanQuestion);
                    } catch (Throwable $ollamaEx) {
                        $answer = $this->formatConversationalResponse($filteredRecords, $cleanQuestion);
                    }
                } else {
                    $answer = $this->formatConversationalResponse($filteredRecords, $cleanQuestion);
                }
            }

            $cleanAnswer = trim($answer);

            // Sanitasi Keselamatan & Estetika Chatbot: Buang sebarang kebocoran nombor id atau pemisah paip SQL
            $cleanAnswer = preg_replace('/\b(id|ID):\s*\d+(\s*\|\s*)?/i', '', $cleanAnswer);
            $cleanAnswer = preg_replace('/(\s*\|\s*)+/', ' — ', $cleanAnswer);

            // Sanitasi Keselamatan Tambahan: Hapuskan sebarang cubaan LLM memetik nama jadual teknikal
            $forbiddenTechnicalPatterns = [
                '/\b(jadual|table|laman|website|sumber)\s+["\']?(products|store_hours|inventory_items|categories)["\']?/i',
                '/\b(products|store_hours)\b/i',
            ];
            foreach ($forbiddenTechnicalPatterns as $pattern) {
                if (preg_match($pattern, $cleanAnswer)) {
                    // Jika LLM membocorkan nama jadual, gantikan dengan istilah mesra pengguna
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
                'answer' => "Maaf, perkhidmatan chatbot sedang mengalami kesulitan teknikal buat sementara waktu. Sila cuba lagi sebentar lagi.",
                'sources' => [],
            ];
        }
    }

    /**
     * Urai teks kandungan rekod kepada senarai pasangan atribut
     */
    private function parseRecordContent(string $content): array
    {
        $clean = preg_replace('/^\[Sumber:\s*[^\]]+\]\s*/i', '', $content);
        $parts = explode(' | ', $clean);
        $fields = [];

        foreach ($parts as $part) {
            $pos = strpos($part, ': ');
            if ($pos !== false) {
                $key = strtolower(trim(substr($part, 0, $pos)));
                $val = trim(substr($part, $pos + 2));

                // Jangan simpan kunci teknikal id
                if ($key === 'id') {
                    continue;
                }

                $fields[$key] = $val;
            } else {
                $trimmed = trim($part);
                if (!preg_match('/^id:\s*\d+$/i', $trimmed) && $trimmed !== '') {
                    $fields[] = $trimmed;
                }
            }
        }

        return $fields;
    }

    /**
     * Format rekod pangkalan data kepada jawapan chatbot yang mesra, bersahabat dan semulajadi tanpa ID
     */
    private function formatConversationalResponse(array $filteredRecords, string $question): string
    {
        if (empty($filteredRecords)) {
            return "Maaf, maklumat berkenaan pertanyaan anda tidak ditemui dalam rekod perniagaan kami buat masa ini. Sila hubungi khidmat staf kami untuk bantuan lanjut.";
        }

        $byCategory = [
            'products' => [],
            'promotions' => [],
            'store_policies' => [],
            'store_hours' => [],
            'others' => [],
        ];

        foreach ($filteredRecords as $rec) {
            $parsed = $this->parseRecordContent($rec['content']);
            if (empty($parsed)) {
                continue;
            }
            $table = $rec['source_table'] ?? 'others';
            if (isset($byCategory[$table])) {
                $byCategory[$table][] = $parsed;
            } else {
                $byCategory['others'][] = $parsed;
            }
        }

        $sections = [];

        // 1. Produk
        if (!empty($byCategory['products'])) {
            $lines = ["👟 **Pilihan Produk:**"];
            foreach ($byCategory['products'] as $prod) {
                $name = $prod['name'] ?? ($prod['title'] ?? 'Produk');
                $cat = !empty($prod['category']) ? " ({$prod['category']})" : "";
                
                $priceStr = "";
                if (isset($prod['price']) && is_numeric($prod['price'])) {
                    $priceStr = "RM " . number_format((float)$prod['price'], 2);
                }

                $stockStr = "";
                if (isset($prod['stock'])) {
                    $stockNum = (int)$prod['stock'];
                    if ($stockNum > 0) {
                        $stockStr = "Baki stok: {$stockNum} unit";
                    } else {
                        $stockStr = "Habis stok buat masa ini";
                    }
                }

                $metaItems = array_filter([$priceStr, $stockStr]);
                $metaLine = !empty($metaItems) ? " — " . implode(' · ', $metaItems) : "";

                $desc = !empty($prod['description']) ? "\n  " . $prod['description'] : "";
                $lines[] = "• **{$name}**{$cat}{$metaLine}{$desc}";
            }
            $sections[] = implode("\n", $lines);
        }

        // 2. Promosi
        if (!empty($byCategory['promotions'])) {
            $lines = ["🎉 **Tawaran & Promosi:**"];
            foreach ($byCategory['promotions'] as $promo) {
                $pName = $promo['promo_name'] ?? ($promo['title'] ?? 'Promosi Istimewa');
                $rate = !empty($promo['discount_rate']) ? " ({$promo['discount_rate']})" : "";
                $valid = !empty($promo['valid_until']) ? "Sah sehingga {$promo['valid_until']}." : "";
                $terms = !empty($promo['terms']) ? "Syarat: " . $promo['terms'] : "";

                $details = implode(' ', array_filter([$valid, $terms]));
                $detailLine = !empty($details) ? "\n  {$details}" : "";
                $lines[] = "• **{$pName}**{$rate}{$detailLine}";
            }
            $sections[] = implode("\n", $lines);
        }

        // 3. Polisi (Pemulangan, Penghantaran, Jaminan, Pembayaran)
        if (!empty($byCategory['store_policies'])) {
            $lines = ["📌 **Polisi Perniagaan:**"];
            foreach ($byCategory['store_policies'] as $pol) {
                $pTitle = $pol['policy_title'] ?? ($pol['title'] ?? 'Polisi');
                $pCat = !empty($pol['category']) ? " ({$pol['category']})" : "";
                $pDetails = !empty($pol['details']) ? ": {$pol['details']}" : "";
                $lines[] = "• **{$pTitle}**{$pCat}{$pDetails}";
            }
            $sections[] = implode("\n", $lines);
        }

        // 4. Waktu Operasi
        if (!empty($byCategory['store_hours'])) {
            $lines = ["⏰ **Waktu Operasi Kedai:**"];
            foreach ($byCategory['store_hours'] as $hours) {
                $day = $hours['day_name'] ?? 'Setiap Hari';
                $open = $hours['opening_time'] ?? '';
                $close = $hours['closing_time'] ?? '';
                $status = !empty($hours['status']) ? " ({$hours['status']})" : "";
                $notes = !empty($hours['notes']) ? "\n  Nota: {$hours['notes']}" : "";
                $timeStr = ($open && $close) ? " {$open} - {$close}" : "";
                $lines[] = "• **{$day}**{$timeStr}{$status}{$notes}";
            }
            $sections[] = implode("\n", $lines);
        }

        // 5. Jadual lain / Lain-lain
        if (!empty($byCategory['others'])) {
            $lines = ["ℹ️ **Maklumat Tambahan:**"];
            foreach ($byCategory['others'] as $other) {
                $title = $other['title'] ?? ($other['name'] ?? null);
                unset($other['id'], $other['title'], $other['name']);
                $descParts = [];
                foreach ($other as $k => $v) {
                    $label = ucfirst(str_replace('_', ' ', (string)$k));
                    $descParts[] = "{$label}: {$v}";
                }
                if ($title) {
                    $lines[] = "• **{$title}** — " . implode(', ', $descParts);
                } else {
                    $lines[] = "• " . implode(', ', $descParts);
                }
            }
            $sections[] = implode("\n", $lines);
        }

        $intro = "Hai! Berdasarkan rekod maklumat perniagaan kami, ini perincian yang berkaitan:";
        $closing = "Ada apa-apa lagi soalan atau maklumat lain yang boleh saya bantu?";

        return $intro . "\n\n" . implode("\n\n", $sections) . "\n\n" . $closing;
    }
}
