<?php
/**
 * Core Database Service Wrapper
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';

class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection === null) {
            self::$connection = db();
        }
        return self::$connection;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $pdo = self::connect();
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('[Database Error] ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw new RuntimeException('A database error occurred. Details have been logged.');
        }
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    public static function execute(string $sql, array $params = []): bool
    {
        self::query($sql, $params);
        return true;
    }

    public static function lastInsertId(): string
    {
        return self::connect()->lastInsertId();
    }

    public static function beginTransaction(): bool
    {
        return self::connect()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::connect()->commit();
    }

    public static function rollBack(): bool
    {
        return self::connect()->rollBack();
    }
}
