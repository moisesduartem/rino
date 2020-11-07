<?php
declare(strict_types=1);

namespace Rino;

/**
 * Class Schema
 * @package Rino
 * @author Moisés Mariano
 * @github /moisesduartem/rino
 */
class Schema
{
    /**
     * @var object
     */
    protected static object $credentials;

    /**
     * Schema constructor.
     * @param object $credentials
     */
    public function __construct(object $credentials)
    {
        static::$credentials = $credentials;
    }

    /**
     * @param string $sql
     * @param array $params
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []) : \PDOStatement
    {
        $pdo = static::getConnection(static::$credentials);
        $stmt = $pdo->prepare($sql);
        if ($params != []) {
            $this->bindParams($stmt, $params);
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * @param \PDOStatement $stmt
     * @param array $params
     */
    private function bindParams(\PDOStatement $stmt, array $params) : void
    {
        foreach ($params as $key => $value) {
            $stmt->bindParam($key, $value);
        }
    }

    /**
     * @return array
     */
    public function showTables()
    {
        return $this->query('show tables')->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param int $value
     */
    protected function setForeignKeyChecks(int $value)
    {
        $this->query('set foreign_key_checks=' . (string) $value);
    }

    /**
     * @param object $credentials
     * @return \PDO
     */
    protected static function getConnection(object $credentials) : \PDO
    {
        return new \PDO (
            "{$credentials->driver}:host={$credentials->host};port={$credentials->port};dbname={$credentials->database};charset=utf8",
            $credentials->username,
            $credentials->password
        );
    }
}