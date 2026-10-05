<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOStatement;

final class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $driver = (string)($config['driver'] ?? 'mysql');
        $dsn = $this->buildDsn($driver, $config);

        $this->pdo = new PDO(
            $dsn,
            (string)($config['username'] ?? ''),
            (string)($config['password'] ?? ''),
            $config['options'] ?? []
        );
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function buildDsn(string $driver, array $config): string
    {
        if ($driver === 'sqlite') {
            return 'sqlite:' . (string)($config['database'] ?? ':memory:');
        }

        return sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $driver,
            (string)($config['host'] ?? '127.0.0.1'),
            (string)($config['port'] ?? '3306'),
            (string)($config['database'] ?? ''),
            (string)($config['charset'] ?? 'utf8mb4')
        );
    }
}
