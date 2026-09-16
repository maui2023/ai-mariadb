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
            $userHistorySnippets = [];
            foreach ($recentHistory as $msg) {
                $isUser = ($msg['role'] ?? '') === 'user';
                $role = $isUser ? 'Pelanggan' : 'Pembantu';
                $content = trim((string)($msg['content'] ?? ''));
                if ($content !== '') {
                    $historyLines[] = "{$role}: {$content}";
                    // Hanya kumpul mesej pengguna untuk konteks carian
                    if ($isUser && $content !== $cleanQuestion) {
                        $userHistorySnippets[] = $content;
                    }
                }
            }

            // Semak jika soalan sekarang adalah soalan rujukan lanjutan tanpa subjek (cth: "berapa harga?", "ada saiz 42?", "warna apa?")
            $isAnaphoric = (bool)preg_match('/\b(berapa|harga|saiz|ada|stok|warna|lagi|tu|itu|ni|ini|dia|ia|tadi)\b/ui', $cleanQuestion)
                && !preg_match('/\b(kasut|baju|kurung|kemeja|seluar|jubah|nimbus|formal|melayu|pos|penghantaran|pemulangan|tukar|pulang|operasi|waktu)\b/ui', $cleanQuestion);

            if ($isAnaphoric && !empty($userHistorySnippets)) {
                $lastUserMsg = end($userHistorySnippets);
                $contextualSearchText = $lastUserMsg . ' ' . $cleanQuestion;
            }
        }

        // 0. Semak niat khas atau soalan luar bidang (Identiti bot, Sekatan Coding, Salam, atau Soalan Umum)
        $specialResponse = $this->checkSpecialIntent($cleanQuestion);
        if ($specialResponse !== null) {
            return [
                'answer' => $specialResponse,
                'sources' => [],
            ];
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
                $relevantRecords = VectorSearch::search($questionVector, limit: 8, threshold: 0.28);
            } else {
                // Fallback carian kata kunci pintar jika servis embedding AI tidak dapat diakses
                $relevantRecords = VectorSearch::searchByKeyword($contextualSearchText, limit: 8);
            }

            $isFashionOrStylingAdvice = $this->isFashionOrStylingAdvice($cleanQuestion);

            // 3. Sekiranya tiada rekod yang sepadan atau skor terlalu rendah
            if (empty($relevantRecords) || (isset($relevantRecords[0]['score']) && $relevantRecords[0]['score'] < 0.32 && !$this->hasDirectKeywordMatch($cleanQuestion, $relevantRecords[0]))) {
                // Gunakan keupayaan AI untuk soalan gaya, padanan warna, tips fesyen atau penjagaan produk
                if ($isFashionOrStylingAdvice) {
                    return [
                        'answer' => $this->generateFashionStylingAdvice($cleanQuestion),
                        'sources' => [],
                    ];
                }

                // Untuk soalan umum lain di luar bidang
                return [
                    'answer' => "Maaf, kami tidak menjawab soalan umum di luar bidang perniagaan kami. Sila ajukan soalan berkaitan katalog produk, promosi, polisi pemulangan, atau waktu operasi butik kami.",
                    'sources' => [],
                ];
            }

            // Pilih dan kurasi rekod secara pintar mengikut keperluan soalan
            $topScore = $relevantRecords[0]['score'];
            $filteredRecords = [];
            $productCount = 0;

            // Semak niat soalan khusus untuk setiap jadual
            $topSourceTable = $relevantRecords[0]['source_table'];
            $isPolicyQuery = (bool)preg_match('/\b(polisi|policy|tukar|pulang|pemulangan|pos|penghantaran|kos|caj|rosak|syarat)\b/i', $cleanQuestion);
            $isHoursQuery = (bool)preg_match('/\b(waktu|jam|masa|buka|tutup|hari|operasi|ahad|isnin|selasa|rabu|khamis|jumaat|sabtu)\b/i', $cleanQuestion);
            $isPromoQuery = (bool)preg_match('/\b(diskaun|diskuan|promosi|promo|jualan|potongan|voucher|kupon|percuma|free|tawaran)\b/i', $cleanQuestion);

            foreach ($relevantRecords as $rec) {
                $table = $rec['source_table'];
                if ($table === 'products') {
                    if ($topSourceTable === 'products' || (!$isPolicyQuery && !$isHoursQuery)) {
                        $isRelevantProduct = ($rec['score'] >= ($topScore * 0.75) && $rec['score'] >= 0.30);
                        if ($isRelevantProduct && $productCount < 2) {
                            $filteredRecords[] = $rec;
                            $productCount++;
                        }
                    }
                } elseif ($table === 'promotions') {
                    if ($isPromoQuery || $topSourceTable === 'promotions') {
                        $filteredRecords[] = $rec;
                    }
                } elseif ($table === 'store_policies') {
                    if ($isPolicyQuery || $topSourceTable === 'store_policies') {
                        $filteredRecords[] = $rec;
                    }
                } elseif ($table === 'store_hours') {
                    if ($isHoursQuery || $topSourceTable === 'store_hours') {
                        $filteredRecords[] = $rec;
                    }
                }
            }

            if (empty($filteredRecords)) {
                $filteredRecords = array_slice($relevantRecords, 0, 2);
            }

            // Sekiranya pengguna menyatakan saiz khusus (cth: 42, 43, S, M, L, XL), utamakan rekod yang sepadan dengan saiz tersebut
            if (preg_match('/\b(?:saiz|size)?\s*(\d{2}|[smlx]+)\b/i', $cleanQuestion, $sizeMatch)) {
                $requestedSize = strtolower(trim($sizeMatch[1]));
                $sizeMatchedRecords = [];
                foreach ($filteredRecords as $rec) {
                    $recContentLower = strtolower($rec['title'] . ' ' . $rec['content']);
                    if (str_contains($recContentLower, $requestedSize)) {
                        $sizeMatchedRecords[] = $rec;
                    }
                }
                if (!empty($sizeMatchedRecords)) {
                    $filteredRecords = $sizeMatchedRecords;
                }
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

            // 5. Arahan System Prompt Khidmat Pelanggan Mesra & Tepat Berdasarkan Fakta
            $historyBlock = "";
            if (!empty($historyLines)) {
                $historyBlock = "\n[SEJARAH PERBUALAN LEPAS]\n" . implode("\n", $historyLines) . "\n";
            }

            $systemPrompt = <<<PROMPT
Anda ialah Pembantu Maya rasmi butik kami.
Jawab pertanyaan pelanggan berasaskan maklumat di bawah sahaja.
Peraturan:
1. Jawab dalam 1 atau 2 ayat Bahasa Melayu yang sopan, ringkas dan tepat.
2. Nyatakan nama produk, baki stok, dan harga dalam RM mengikut maklumat yang ada.
3. JANGAN reka maklumat luar atau andaian sendiri yang tiada dalam rekod.
4. JANGAN sesekali memaparkan ID pangkalan data teknikal, nama jadual, atau kod programming.

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

            // Sanitasi Keselamatan: Sekat sebarang blok kod pengaturcaraan yang cuba dijana oleh LLM
            if (preg_match('/```(python|php|javascript|js|html|css|sql|bash|c|java|cpp)?/i', $cleanAnswer)) {
                $cleanAnswer = "Maaf, kami tidak menjawab soalan umum atau pertanyaan berkaitan pengaturcaraan (coding). Saya sedia membantu anda mengenai maklumat produk, saiz, harga, promosi atau waktu operasi butik kami.";
            }

            // Sanitasi Keselamatan & Estetika Chatbot: Buang sebarang kebocoran nombor id atau pemisah paip SQL
            $cleanAnswer = preg_replace('/\b(id|ID):\s*\d+(\s*\|\s*)?/i', '', $cleanAnswer);
            $cleanAnswer = preg_replace('/(\s*\|\s*)+/', ' — ', $cleanAnswer);

            // Keselamatan Halusinasi: Jika LLM tersilap menolak soalan sah sebagai soalan umum, guna jawapan fakta berstruktur
            if (preg_match('/tidak menjawab soalan.*(stok|kasut|baju|harga|saiz|produk|operasi|kedai|butik|waktu)/iu', $cleanAnswer)) {
                $cleanAnswer = $this->formatConversationalResponse($filteredRecords, $cleanQuestion);
            }

            // Sanitasi Keselamatan Tambahan: Hapuskan sebarang cubaan LLM memetik nama jadual teknikal
            $forbiddenTechnicalPatterns = [
                '/\b(jadual|table|laman|website|sumber)\s+["\']?(products|store_hours|inventory_items|categories)["\']?/i',
                '/\b(products|store_hours)\b/i',
            ];
            foreach ($forbiddenTechnicalPatterns as $pattern) {
                if (preg_match($pattern, $cleanAnswer)) {
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
     * Semak sama ada soalan berkaitan gaya fesyen, padanan warna, tips atau penjagaan pakaian/kasut
     */
    private function isFashionOrStylingAdvice(string $question): bool
    {
        $q = mb_strtolower(trim($question));
        $patterns = [
            '/\b(padanan|padan|matching|fesyen|fashion|gaya|style)\b/u',
            '/\b(tips|petua|cadangan|cadang|recommend|advice)\b/u',
            '/\b(warna\s+apa|warna\s+yang\s+sesuai|sesuai\s+dengan|sesuai\s+untuk)\b/u',
            '/\b(kenduri|majlis|kahwin|perkahwinan|pejabat|formal|santai|casual|raya)\b/u',
            '/\b(cara\s+(jaga|penjagaan|cuci|basuh|bersihkan|simpan))\b/u',
            '/\b(kasut\s+kulit|baju\s+melayu|kain\s+cotton|material|fabrik|leather)\b/u',
            '/\b(ukur\s+saiz|pilih\s+saiz|saiz\s+sesuai|cutting|potongan)\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $q)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Semak jika soalan mengandungi kata kunci yang benar-benar ada dalam rekod teratas
     */
    private function hasDirectKeywordMatch(string $question, array $record): bool
    {
        $q = mb_strtolower(trim($question));
        $tokens = preg_split('/[\s,\.\?\!\-\_\:\;\/\|\(\)\[\]]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
        $stopWords = [
            'ada', 'di', 'ke', 'dari', 'yang', 'dan', 'atau', 'ini', 'itu', 'untuk', 'pada', 
            'saya', 'awak', 'kami', 'tak', 'tidak', 'kah', 'pun', 'apakah', 'siapakah', 'bagaimanakah',
            'siapa', 'nama', 'anda', 'kamu', 'bot', 'ai', 'bila', 'berapa', 'mana', 'apa'
        ];
        $meaningfulTokens = array_filter($tokens, fn($t) => mb_strlen($t) > 2 && !in_array($t, $stopWords, true));
        if (empty($meaningfulTokens)) {
            return false;
        }

        $recordText = mb_strtolower(($record['title'] ?? '') . ' ' . ($record['content'] ?? ''));
        foreach ($meaningfulTokens as $token) {
            if (str_contains($recordText, $token)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Jana nasihat gaya fesyen dan padanan menggunakan kepintaran AI tanpa halusinasi pangkalan data
     */
    private function generateFashionStylingAdvice(string $question): string
    {
        $prompt = <<<PROMPT
Anda ialah Pembantu Maya dan penasihat gaya rasmi butik fesyen kami.
Pelanggan meminta panduan gaya, padanan warna pakaian, tips saiz, atau penjagaan produk.
Tugas anda:
1. Berikan nasihat gaya atau tips yang mesra, elegan dan praktikal dalam 2 hingga 3 ayat ringkas dalam Bahasa Melayu.
2. Jemput pelanggan untuk meneroka koleksi pakaian atau kasut di butik kami sekiranya mereka berminat.
3. JANGAN sesekali memaparkan kod pengaturcaraan, ID teknikal, atau maklumat palsu.
PROMPT;

        try {
            $response = $this->ai->chat($prompt, $question);
            return trim($response);
        } catch (Throwable $e) {
            if ($this->fallbackOllama !== null && $this->fallbackOllama->isAvailable()) {
                try {
                    return trim($this->fallbackOllama->chat($prompt, $question));
                } catch (Throwable $oEx) {
                    // Terus ke jawapan sandaran sopan
                }
            }
            return "Untuk pilihan gaya yang kemas dan versatil, anda boleh memadankan warna-warna neutral seperti hitam, putih, atau kelabu untuk majlis formal mahupun santai. Jemput layari katalog butik kami untuk melihat koleksi pakaian dan kasut terkini!";
        }
    }

    /**
     * Semak niat khas atau soalan luar bidang (Identiti bot, Sekatan Coding, Salam, atau Soalan Umum)
     * untuk melindungi domain perniagaan dan menghapuskan halusinasi.
     */
    private function checkSpecialIntent(string $question): ?string
    {
        $q = mb_strtolower(trim($question));

        // 1. Soalan Pengaturcaraan / Coding / Teknikal Komputer (SEKAT SEPENUHNYA)
        $codingPatterns = [
            '/\b(coding|pengaturcaraan|programming|programmer|developer|software)\b/u',
            '/\b(kod|code|script|skrip|function|loop|fungsi|syntax|algoritma|algorithm)\b/u',
            '/\b(python|php|javascript|typescript|js|ts|java|ruby|golang|go|rust|html|css|sql|bash|powershell|react|vue|laravel|flutter|node|c\+\+|c\#)\b/u',
            '/(c\+\+|c\#)/u',
            '/\b(tuliskan|buatkan|bina|generate|create|write)\s+(kod|code|script|function|loop|api|class|program)\b/u',
            '/\b(for\s+loop|while\s+loop|syntax\s+error|debug|stack\s*trace)\b/u',
            '/\b(select\s+\*|drop\s+table|insert\s+into|delete\s+from|create\s+table)\b/u',
        ];
        foreach ($codingPatterns as $pattern) {
            if (preg_match($pattern, $q)) {
                return "Maaf, kami tidak menjawab soalan umum atau pertanyaan berkaitan pengaturcaraan (coding). Saya merupakan pembantu maya butik ini dan sedia membantu anda mengenai produk, saiz, harga, promosi atau waktu operasi butik kami.";
            }
        }

        // 2. Soalan Identiti & Nama Bot (Bijak: tidak bernama & nyatakan peranan rasmi)
        $identityPatterns = [
            '/\b(siapa|siapakah|apa|apakah)\s+(nama\s+)?(anda|awak|kamu|bot|ai|sistem)\b/u',
            '/\b(nama\s+)(anda|awak|kamu|bot|ai)\s*(siapa|apa|siapakah|apakah)?\b/u',
            '/\b(anda|awak|kamu|bot)\s+(ada\s+nama|nama\s+apa|siapa|siapakah)\b/u',
            '/\b(awak|anda|kamu)\s+ni\s+(siapa|apa)\b/u',
            '/\b(siapa\s+kamu|siapa\s+awak|siapa\s+anda)\b/u',
            '/\b(kenali\s+anda|kenali\s+awak|siapa\s+cipta\s+anda|siapa\s+buat\s+anda)\b/u',
            '/\b(anda|awak)\s+(robot|manusia|ai)\s*(ke|kah)?\b/u',
            '/^(siapa|apa)\s+nama\??$/u',
        ];
        foreach ($identityPatterns as $pattern) {
            if (preg_match($pattern, $q)) {
                return "Saya tidak mempunyai nama peribadi. Saya ialah Pembantu Maya rasmi bagi butik ini, sedia membantu anda menyemak maklumat produk, saiz, harga, stok, promosi atau waktu operasi kedai kami. Ada apa-apa yang boleh saya bantu mengenai pesanan atau pilihan produk anda?";
            }
        }

        // 3. Salam Santun / Sapaan Mesra (Hanya jika pertanyaan pendek tanpa kata kunci produk)
        $greetingPatterns = [
            '/^(hai|hello|helo|hi|hola|hey|salam|assalamualaikum|assalam|slm)\b/u',
            '/^(selamat\s+(pagi|tengah\s*hari|petang|malam))\b/u',
            '/^(apa\s+khabar|khabar\s+baik)\b/u',
            '/^(terima\s*kasih|tq|thanks|thank\s+you)\b/u',
        ];
        $tokens = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
        if (count($tokens) <= 4) {
            foreach ($greetingPatterns as $pattern) {
                if (preg_match($pattern, $q)) {
                    if (str_contains($q, 'terima kasih') || str_contains($q, 'tq') || str_contains($q, 'thank')) {
                        return "Sama-sama! Senang dapat melayani anda. Sila beritahu saya jika anda memerlukan maklumat lain mengenai produk atau tawaran promosi butik kami. 😊";
                    }
                    return "Hai! Selamat datang ke butik kami. Saya sedia membantu anda mengenai produk, saiz, harga, tawaran diskaun atau waktu operasi kedai kami. Apa yang boleh saya bantu hari ini? 😊";
                }
            }
        }

        // 4. Soalan Umum di luar bidang perniagaan (Trivia, Cuaca, Politik, Sains, Resipi, Berita Dunia)
        $outOfScopePatterns = [
            '/\b(cuaca\s+hari\s+ini|ramalan\s+cuaca|suhu\s+hari\s+ini|hujan\s+ke\s+hari\s+ini)\b/u',
            '/\b(siapa\s+perdana\s+menteri|siapa\s+presiden|menteri\s+besar|ahli\s+parlimen)\b/u',
            '/\b(ibu\s+negara\s+|negara\s+mana|jarak\s+bumi|sistem\s+suria|planet)\b/u',
            '/\b(resepi|resipi|cara\s+masak|masakan|menu\s+makan)\b/u',
            '/\b(politik|pilihan\s+raya|parti\s+politik|kerajaan|parlimen)\b/u',
            '/\b(kira\s+\d+\s*[\+\-\*\/]\s*\d+|formula\s+matematik|teorem)\b/u',
            '/\b(ceritakan\s+kisah|tulis\s+cerita|karang\s+esei|buat\s+puisi|lirik\s+lagu)\b/u',
            '/\b(fotosintesis|graviti|sel\s+haiwan|organisma|atom|molekul)\b/u',
            '/\b(sukan\s+bola|piala\s+dunia|premier\s+league|liga\s+super)\b/u',
        ];
        foreach ($outOfScopePatterns as $pattern) {
            if (preg_match($pattern, $q)) {
                return "Maaf, kami tidak menjawab soalan umum di luar bidang perniagaan kami. Sila ajukan soalan berkaitan katalog produk, promosi, polisi pemulangan, atau waktu operasi butik kami.";
            }
        }

        return null;
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
