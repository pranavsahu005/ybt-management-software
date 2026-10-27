<?php
/**
 * YBT Management Software — Database Connection (PDO Singleton)
 */

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $config = require YBT_ROOT . '/config/config.php';
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['db_host'],
                $config['db_port'],
                $config['db_name'],
                $config['db_charset']
            );
            try {
                self::$instance = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                if ($config['debug']) {
                    throw $e;
                }
                error_log('Database connection failed: ' . $e->getMessage());
                die('Database connection error. Please check configuration.');
            }
        }
        return self::$instance;
    }

    /**
     * Shorthand query helper
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch all rows
     */
    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Fetch single row
     */
    public static function fetch(string $sql, array $params = []): ?array {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    /**
     * Fetch single column value
     */
    public static function fetchColumn(string $sql, array $params = []) {
        return self::query($sql, $params)->fetchColumn();
    }

    /**
     * Insert and return last insert ID
     * Supports both table name + key-value array OR raw SQL + bound parameters
     */
    public static function insert(string $tableOrSql, array $paramsOrData = []): int {
        $trimmed = ltrim($tableOrSql);
        if (stripos($trimmed, 'INSERT ') === 0) {
            self::query($tableOrSql, $paramsOrData);
            return (int)self::getInstance()->lastInsertId();
        }

        // Table-name format: Database::insert('table_name', ['col' => 'val'])
        $table = str_replace('`', '``', $tableOrSql);
        $columns = array_keys($paramsOrData);
        if (empty($columns)) {
            throw new InvalidArgumentException("Cannot insert empty dataset into {$tableOrSql}.");
        }

        $escapedCols = array_map(fn($col) => '`' . str_replace('`', '``', (string)$col) . '`', $columns);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', $escapedCols),
            implode(', ', $placeholders)
        );

        self::query($sql, array_values($paramsOrData));
        return (int)self::getInstance()->lastInsertId();
    }

    /**
     * Update rows in table with key-value data and WHERE condition
     */
    public static function update(string $table, array $data, string $where = '', array $whereParams = []): int {
        if (empty($data)) {
            return 0;
        }

        $setParts = [];
        $params = [];
        foreach ($data as $col => $val) {
            $setParts[] = '`' . str_replace('`', '``', (string)$col) . '` = ?';
            $params[] = $val;
        }

        $escapedTable = str_replace('`', '``', $table);
        $sql = sprintf('UPDATE `%s` SET %s', $escapedTable, implode(', ', $setParts));

        if (!empty($where)) {
            $sql .= ' WHERE ' . $where;
            foreach ($whereParams as $wp) {
                $params[] = $wp;
            }
        }

        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete rows from table with required WHERE condition
     */
    public static function delete(string $table, string $where, array $whereParams = []): int {
        if (empty(trim($where))) {
            throw new InvalidArgumentException('WHERE clause is strictly required for Database::delete.');
        }

        $escapedTable = str_replace('`', '``', $table);
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $escapedTable, $where);
        $stmt = self::query($sql, $whereParams);
        return $stmt->rowCount();
    }

    /**
     * Get last inserted ID
     */
    public static function lastInsertId(): string {
        return self::getInstance()->lastInsertId();
    }

    /**
     * Check if currently in an active transaction
     */
    public static function inTransaction(): bool {
        return self::getInstance()->inTransaction();
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit(): bool {
        return self::getInstance()->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): bool {
        return self::getInstance()->rollBack();
    }
}
