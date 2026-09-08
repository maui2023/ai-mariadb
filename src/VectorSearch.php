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
}
