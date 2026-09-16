<?php
declare(strict_types=1);

namespace AiMariaDb;

class VectorSearch
{
    /**
     * Kira Kesamaan Kosin (Cosine Similarity) antara dua vektor nombor
     * Nilai antara -1.0 hingga 1.0 (1.0 = makna sama sepenuhnya)
     * 
     * @param float[] $vecA
     * @param float[] $vecB
     */
    public static function cosineSimilarity(array $vecA, array $vecB): float
    {
        $count = count($vecA);
        if ($count === 0 || $count !== count($vecB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = (float)$vecA[$i];
            $b = (float)$vecB[$i];

            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Cari rekod dalam storan vektor tempatan yang paling relevan dengan vektor soalan
     * 
     * @param float[] $queryVector Vektor soalan pengguna
     * @param int $limit Bilangan rekod teratas yang dicari
     * @param float $threshold Nilai kesamaan minimum (lalai: 0.3)
     * @return array Rekod relevan berserta skor kesamaan
     */
    public static function search(array $queryVector, int $limit = 5, float $threshold = 0.3): array
    {
        $pdo = Database::getLocalPdo();
        $stmt = $pdo->query("SELECT id, connection_id, source_table, source_id, title, content, vector FROM ai_knowledge_vectors");
        $rows = $stmt->fetchAll();

        $results = [];
        $seenKeys = [];

        foreach ($rows as $row) {
            $recordVector = json_decode($row['vector'], true);
            if (!is_array($recordVector)) {
                continue;
            }

            $score = self::cosineSimilarity($queryVector, $recordVector);

            if ($score >= $threshold) {
                $uniqueKey = $row['source_table'] . ':' . $row['source_id'];
                if (isset($seenKeys[$uniqueKey])) {
                    continue;
                }
                $seenKeys[$uniqueKey] = true;

                $results[] = [
                    'id' => $row['id'],
                    'source_table' => $row['source_table'],
                    'source_id' => $row['source_id'],
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'score' => round($score, 4),
                ];
            }
        }

        // Susun mengikut skor tertinggi ke terendah
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Carian berasaskan kata kunci pintar sekiranya servis embedding AI tergendala
     * 
     * @param string $query Teks soalan atau carian pengguna
     * @param int $limit Bilangan rekod maksimum
     * @return array Rekod relevan dengan format yang konsisten
     */
    public static function searchByKeyword(string $query, int $limit = 5): array
    {
        $pdo = Database::getLocalPdo();
        $stmt = $pdo->query("SELECT id, connection_id, source_table, source_id, title, content FROM ai_knowledge_vectors");
        $rows = $stmt->fetchAll();

        $cleanQuery = mb_strtolower(trim($query));
        $tokens = preg_split('/[\s,\.\?\!\-\_\:\;\/\|\(\)\[\]]+/u', $cleanQuery, -1, PREG_SPLIT_NO_EMPTY);
        $stopWords = [
            'ada', 'di', 'ke', 'dari', 'yang', 'dan', 'atau', 'ini', 'itu', 'untuk', 'pada', 
            'saya', 'awak', 'kami', 'tak', 'tidak', 'kah', 'pun', 'apakah', 'siapakah', 'bagaimanakah',
            'the', 'is', 'a', 'an', 'what', 'how', 'when', 'where', 'bila', 'berapa'
        ];
        $keywords = array_values(array_filter($tokens, fn($w) => mb_strlen($w) > 1 && !in_array($w, $stopWords, true)));

        if (empty($keywords)) {
            $keywords = [$cleanQuery];
        }

        $results = [];
        $seenKeys = [];

        foreach ($rows as $row) {
            $uniqueKey = $row['source_table'] . ':' . $row['source_id'];
            if (isset($seenKeys[$uniqueKey])) {
                continue;
            }

            $titleLower = mb_strtolower((string)$row['title']);
            $contentLower = mb_strtolower((string)$row['content']);
            $score = 0.0;

            // Semak padanan penuh frasa
            if (str_contains($titleLower, $cleanQuery)) {
                $score += 0.8;
            } elseif (str_contains($contentLower, $cleanQuery)) {
                $score += 0.6;
            }

            // Semak padanan setiap kata kunci
            $matchedTokens = 0;
            foreach ($keywords as $kw) {
                if (str_contains($titleLower, $kw)) {
                    $score += 0.4;
                    $matchedTokens++;
                } elseif (str_contains($contentLower, $kw)) {
                    $score += 0.25;
                    $matchedTokens++;
                }
            }

            if ($score > 0) {
                $seenKeys[$uniqueKey] = true;
                $results[] = [
                    'id' => $row['id'],
                    'source_table' => $row['source_table'],
                    'source_id' => $row['source_id'],
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'score' => round(min(1.0, $score), 4),
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }
}
