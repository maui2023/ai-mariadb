<?php
declare(strict_types=1);

namespace AiMariaDb;

use PDO;
use RuntimeException;
use Throwable;

class Indexer
{
    private OllamaClient $ollama;
    private PDO $localPdo;

    public function __construct(?OllamaClient $ollama = null)
    {
        $this->ollama = $ollama ?? new OllamaClient();
        $this->localPdo = Database::getLocalPdo();
    }

    /**
     * Jalankan proses pengindeksan untuk sambungan database luaran tertentu
     * 
     * @param int $connectionId ID sambungan di ai_db_connections
     * @param callable|null $onProgress Fungsi maklum balas kemajuan: fn(string $msg, int $current, int $total)
     * @return array Ringkasan hasil indeks
     */
    public function indexConnection(int $connectionId, ?callable $onProgress = null): array
    {
        // 1. Ambil maklumat sambungan
        $stmt = $this->localPdo->prepare("SELECT * FROM ai_db_connections WHERE id = :id");
        $stmt->execute(['id' => $connectionId]);
        $conn = $stmt->fetch();

        if (!$conn) {
            throw new RuntimeException("Sambungan ID {$connectionId} tidak ditemui.");
        }

        $selectedTables = json_decode($conn['selected_tables'] ?? '[]', true) ?: [];
        if (empty($selectedTables)) {
            throw new RuntimeException("Tiada jadual yang dipilih untuk diindeks pada sambungan ini.");
        }

        // 2. Sambung ke database luaran
        $extPdo = Database::connectExternal([
            'driver' => $conn['driver'] ?? 'mysql',
            'host' => $conn['db_host'],
            'port' => $conn['db_port'],
            'database' => $conn['db_name'],
            'username' => $conn['db_user'],
            'password' => $conn['db_pass'],
        ]);

        $totalIndexed = 0;
        $totalTables = count($selectedTables);
        $currentTableIndex = 0;

        foreach ($selectedTables as $tableConfig) {
            $currentTableIndex++;
            $tableName = is_array($tableConfig) ? ($tableConfig['name'] ?? '') : (string)$tableConfig;
            $tableName = trim($tableName);

            if (empty($tableName)) {
                continue;
            }

            // KESELAMATAN TEGAR: Tolak jika jadual adalah sensitif (users, etc.)
            if (TableInspector::isTableForbidden($tableName)) {
                if ($onProgress) {
                    $onProgress("Jadual '{$tableName}' DIHALANG demi keselamatan privasi.", $currentTableIndex, $totalTables);
                }
                continue;
            }

            if ($onProgress) {
                $onProgress("Membaca jadual '{$tableName}'...", $currentTableIndex, $totalTables);
            }

            // Ambil semua lajur bagi jadual
            $records = $extPdo->query("SELECT * FROM `{$tableName}`")->fetchAll(PDO::FETCH_ASSOC);
            $recordCount = count($records);

            $recIndex = 0;
            foreach ($records as $row) {
                $recIndex++;
                
                // Cari Primary Key atau ID
                $sourceId = (string)($row['id'] ?? $row['ID'] ?? $row['code'] ?? $recIndex);
                
                // Bina teks konteks bersih tanpa medan terlarang
                $fieldStrings = [];
                $titleCandidate = '';

                foreach ($row as $colName => $val) {
                    if ($val === null || $val === '') {
                        continue;
                    }

                    // Tapis kolum sensitif
                    if (TableInspector::isColumnForbidden($colName)) {
                        continue;
                    }

                    $cleanVal = trim((string)$val);
                    $fieldStrings[] = "{$colName}: {$cleanVal}";

                    // Cari judul yang sesuai untuk paparan
                    if (empty($titleCandidate) && in_array(strtolower($colName), ['name', 'title', 'nama', 'product_name', 'item', 'subject'])) {
                        $titleCandidate = $cleanVal;
                    }
                }

                if (empty($fieldStrings)) {
                    continue;
                }

                $title = !empty($titleCandidate) ? $titleCandidate : "{$tableName} #{$sourceId}";
                $content = "[Sumber: {$tableName}] " . implode(' | ', $fieldStrings);

                try {
                    // Jana embedding melalui Ollama embeddinggemma
                    $vector = $this->ollama->embed($content);
                    $vectorJson = json_encode($vector);

                    // Simpan ke pangkalan data tempatan ai_knowledge_vectors
                    $upsertSql = "
                        INSERT INTO ai_knowledge_vectors (connection_id, source_table, source_id, title, content, vector, updated_at)
                        VALUES (:conn_id, :src_tbl, :src_id, :title, :content, :vector, CURRENT_TIMESTAMP)
                        ON CONFLICT(connection_id, source_table, source_id) DO UPDATE SET
                            title = excluded.title,
                            content = excluded.content,
                            vector = excluded.vector,
                            updated_at = CURRENT_TIMESTAMP
                    ";

                    // Sekiranya menggunakan MySQL secara tempatan
                    if ($this->localPdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                        $upsertSql = "
                            INSERT INTO `ai_knowledge_vectors` (`connection_id`, `source_table`, `source_id`, `title`, `content`, `vector`, `updated_at`)
                            VALUES (:conn_id, :src_tbl, :src_id, :title, :content, :vector, NOW())
                            ON DUPLICATE KEY UPDATE
                                `title` = VALUES(`title`),
                                `content` = VALUES(`content`),
                                `vector` = VALUES(`vector`),
                                `updated_at` = NOW()
                        ";
                    }

                    $insertStmt = $this->localPdo->prepare($upsertSql);
                    $insertStmt->execute([
                        'conn_id' => $connectionId,
                        'src_tbl' => $tableName,
                        'src_id' => $sourceId,
                        'title' => $title,
                        'content' => $content,
                        'vector' => $vectorJson,
                    ]);

                    $totalIndexed++;
                } catch (Throwable $e) {
                    // Teruskan rekod seterusnya jika berlaku kesilapan pada satu rekod
                }
            }
        }

        // Kemaskini tarikh indeks terakhir
        $updateStmt = $this->localPdo->prepare("UPDATE ai_db_connections SET last_synced_at = CURRENT_TIMESTAMP WHERE id = :id");
        $updateStmt->execute(['id' => $connectionId]);

        return [
            'success' => true,
            'total_indexed' => $totalIndexed,
            'tables_processed' => $totalTables,
        ];
    }
}
