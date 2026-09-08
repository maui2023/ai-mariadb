<?php
declare(strict_types=1);

namespace AiMariaDb;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $localPdo = null;

    /**
     * Dapatkan PDO untuk storan tempatan ai-mariadb (menyimpan sambungan & vektor pengetahuan)
     */
    public static function getLocalPdo(): PDO
    {
        if (self::$localPdo !== null) {
            return self::$localPdo;
        }

        $configFile = Config::getDataDir() . '/local_db.json';
        if (file_exists($configFile)) {
            $conf = json_decode(file_get_contents($configFile), true) ?: [];
            if (!empty($conf['driver']) && $conf['driver'] === 'mysql') {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $conf['host'] ?? '127.0.0.1',
                    $conf['port'] ?? 3306,
                    $conf['database'] ?? 'ai_mariadb'
                );
                self::$localPdo = new PDO($dsn, $conf['username'] ?? 'root', $conf['password'] ?? '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::initLocalSchema(self::$localPdo, 'mysql');
                return self::$localPdo;
            }
        }

        // Fallback lalai: SQLite tempatan (pantas, tiada keperluan setup tambahan)
        $dbPath = Config::getDataDir() . '/ai_mariadb.sqlite';
        self::$localPdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        self::initLocalSchema(self::$localPdo, 'sqlite');

        return self::$localPdo;
    }

    /**
     * Cipta sambungan PDO READ-ONLY ke database sistem luaran (MySQL/MariaDB atau SQLite)
     */
    public static function connectExternal(array $config): PDO
    {
        $driver = strtolower($config['driver'] ?? 'mysql');

        if ($driver === 'sqlite') {
            $pdo = new PDO('sqlite:' . $config['database'], null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return $pdo;
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = (int)($config['port'] ?? 3306);
        $dbname = $config['database'] ?? '';
        $user = $config['username'] ?? '';
        $pass = $config['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        // Kuatkuasakan mod READ-ONLY untuk keselamatan sistem asal
        try {
            $pdo->exec("SET SESSION TRANSACTION READ ONLY");
        } catch (\Throwable $e) {
            // Sesetengah versi/pengguna mungkin tidak ada kebenaran SET SESSION, teruskan jika gagal
        }

        return $pdo;
    }

    /**
     * Memulakan skema jadual simpanan tempatan sekiranya belum wujud
     */
    private static function initLocalSchema(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ai_db_connections (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    driver TEXT NOT NULL DEFAULT 'mysql',
                    db_host TEXT NOT NULL,
                    db_port INTEGER NOT NULL DEFAULT 3306,
                    db_name TEXT NOT NULL,
                    db_user TEXT NOT NULL,
                    db_pass TEXT NOT NULL,
                    selected_tables TEXT DEFAULT '[]',
                    status TEXT DEFAULT 'active',
                    last_synced_at TEXT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS ai_knowledge_vectors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    connection_id INTEGER NOT NULL,
                    source_table TEXT NOT NULL,
                    source_id TEXT NOT NULL,
                    title TEXT NOT NULL,
                    content TEXT NOT NULL,
                    vector TEXT NOT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(connection_id, source_table, source_id)
                );
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `ai_db_connections` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `driver` VARCHAR(20) NOT NULL DEFAULT 'mysql',
                    `db_host` VARCHAR(255) NOT NULL,
                    `db_port` INT NOT NULL DEFAULT 3306,
                    `db_name` VARCHAR(100) NOT NULL,
                    `db_user` VARCHAR(100) NOT NULL,
                    `db_pass` TEXT NOT NULL,
                    `selected_tables` TEXT NULL,
                    `status` ENUM('active', 'inactive') DEFAULT 'active',
                    `last_synced_at` DATETIME NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `ai_knowledge_vectors` (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `connection_id` INT UNSIGNED NOT NULL,
                    `source_table` VARCHAR(64) NOT NULL,
                    `source_id` VARCHAR(64) NOT NULL,
                    `title` VARCHAR(255) NOT NULL,
                    `content` TEXT NOT NULL,
                    `vector` LONGTEXT NOT NULL,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_conn_tbl` (`connection_id`, `source_table`),
                    UNIQUE KEY `uk_record` (`connection_id`, `source_table`, `source_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }
    }
}
