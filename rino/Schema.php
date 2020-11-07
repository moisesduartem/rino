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

    protected static function getConnection(object $credentials) : \PDO
    {
        return new \PDO (
            "{$credentials->driver}:host={$credentials->host};port={$credentials->port};dbname={$credentials->database};charset=utf8",
            $credentials->username,
            $credentials->password
        );
    }
}