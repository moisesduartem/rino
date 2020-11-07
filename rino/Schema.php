<?php
declare(strict_types=1);

namespace Rino;

class Schema
{
    protected static object $credentials;

    public function __construct(object $credentials)
    {
        static::$credentials = $credentials;
    }

    public function query(string $sql) : \PDOStatement
    {
        $pdo = static::getConnection(static::$credentials);
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function showTables()
    {
        return $this->query('show tables')->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function setForeignKeyChecks(int $value)
    {
        $this->query('set foreign_key_checks=' . (string) $value);
    }

    protected static function getConnection(object $credentials) : \PDO
    {
        return new \PDO (
            "{$credentials->driver}:host={$credentials->host};port={$credentials->port};dbname={$credentials->database};charset=utf8",
            $credentials->username,
            $credentials->password
        );
    }
}