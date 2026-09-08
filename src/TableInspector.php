<?php
declare(strict_types=1);

namespace AiMariaDb;

use PDO;
use Throwable;

class TableInspector
{
    /**
     * Semak adakah nama jadual mengandungi sebarang kata kunci terlarang (cth: users)
     */
    public static function isTableForbidden(string $tableName): bool
    {
        $normalized = strtolower(trim($tableName));
        
        foreach (Config::STRICT_FORBIDDEN_KEYWORDS as $keyword) {
            // Periksa padanan tepat atau sebahagian (cth: app_users, user_profiles, tbl_user)
            if ($normalized === $keyword || str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Semak adakah nama kolum terlarang / sensitif
     */
    public static function isColumnForbidden(string $columnName): bool
    {
        $normalized = strtolower(trim($columnName));

        foreach (Config::STRICT_FORBIDDEN_COLUMNS as $keyword) {
            if ($normalized === $keyword || str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Imbas pangkalan data luaran dan pulangkan senarai jadual, status keselamatan, dan bilangan baris
     * 
     * @return array Senarai jadual dengan metadata keselamatan
     */
    public static function inspectDatabase(PDO $pdo, string $driver = 'mysql', ?string $databaseName = null): array
    {
        $tables = [];

        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            $rawTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            if ($databaseName) {
                $stmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = :db ORDER BY TABLE_NAME");
                $stmt->execute(['db' => $databaseName]);
                $rawTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $stmt = $pdo->query("SHOW TABLES");
                $rawTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        foreach ($rawTables as $table) {
            $isForbidden = self::isTableForbidden($table);
            $rowCount = 0;
            $columns = [];

            try {
                // Dapatkan bilangan baris rekod
                $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
                $rowCount = (int)$countStmt->fetchColumn();

                // Dapatkan struktur kolum
                if ($driver === 'sqlite') {
                    $colStmt = $pdo->query("PRAGMA table_info(`{$table}`)");
                    $rawCols = $colStmt->fetchAll();
                    foreach ($rawCols as $col) {
                        $colName = $col['name'];
                        $columns[] = [
                            'name' => $colName,
                            'type' => $col['type'],
                            'is_forbidden' => self::isColumnForbidden($colName),
                        ];
                    }
                } else {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
                    $rawCols = $colStmt->fetchAll();
                    foreach ($rawCols as $col) {
                        $colName = $col['Field'];
                        $columns[] = [
                            'name' => $colName,
                            'type' => $col['Type'],
                            'is_forbidden' => self::isColumnForbidden($colName),
                        ];
                    }
                }
            } catch (Throwable $e) {
                // Abaikan jika table tidak boleh diakses
            }

            $tables[] = [
                'name' => $table,
                'is_forbidden' => $isForbidden,
                'forbidden_reason' => $isForbidden ? 'Mengandungi data identiti / pengguna sensitif (Blacklisted)' : null,
                'row_count' => $rowCount,
                'columns' => $columns,
            ];
        }

        return $tables;
    }
}
