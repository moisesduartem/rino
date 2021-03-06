<?php
declare(strict_types=1);

namespace Rino;

use DateTime;
use Exception;
use Rino\Schema;

/**
 * Class Rino
 * @package Rino
 * @author Moisés Mariano
 * @github /moisesduartem/rino
 */
final class Rino extends Schema
{
    /**
     * @var string
     */
    public string $migrationsPath;
    /**
     * @var string
     */
    private string $root;
    /**
     * @var false|string
     */
    private string $example;
    /**
     * @var string
     */
    private string $foreignStuff = '';

    /**
     * Rino constructor.
     * @param string $root
     * @param string $migrationsPath
     * @param object $credentials
     */
    public function __construct(string $root, string $migrationsPath, object $credentials)
    {
        $this->root = $root;
        $this->migrationsPath = $migrationsPath;
        $this->example = file_get_contents(__DIR__ . '/example/migration.php');
        parent::__construct($credentials);
    }

    /**
     *
     */
    public function list()
    {
        $tableNames = $this->showTables();
        echo "tables in '" . static::$credentials->database . "'\n";
        /**
         * List database tables on cli
         */
        foreach ($tableNames as $table) {
            echo $table['Tables_in_' . static::$credentials->database] . PHP_EOL;
        }
    }

    /**
     * @throws Exception
     */
    public function reset()
    {
        /**
         * Receive table names
         * @var array
         */
        $tableNames = $this->showTables();
        /**
         * Build a SQL query to drop tables in 
         * reverse order to avoid foreign key errors
         */
        $sqlToDropTables = '';
        foreach ($tableNames as $table) {
            $sqlToDropTables = 
            'drop table if exists '. $table['Tables_in_' . static::$credentials->database] . ';' . PHP_EOL
            . $sqlToDropTables;
        }
        /**
         * Execute query
         * @var \PDOStatement
         */
        $stmt = $this->query($sqlToDropTables);
        /**
         * If something it's wrong,
         * @throws \Exception
         */
        $this->checkErrors($stmt);
        /**
         * All worked...
         */
        echo "Database " . static::$credentials->database . " has been restored." . PHP_EOL;
    }

    /**
     *
     */
    public function migrate()
    {
        /**
         * Read all migrations filenames
         * @var array
         */
        $migrationFiles = array_slice(scandir($this->migrationsPath), 2);
        /**
         * Parse migration filenames
         */
        foreach ($migrationFiles as $migrationFile) {
            /**
             * Try to execute one by one
             */
            $this->runMigration($migrationFile);
        }
        /**
         * After execute all successfully, shows a message
         */
        echo "Done! Check '" . static::$credentials->database . "' database on ". static::$credentials->driver.".\n";
    }

    /**
     * @param string $migrationFile
     * @throws Exception
     */
    private function runMigration(string $migrationFile)
    {
        /**
         * Require the migration class
         */
        require $this->migrationsPath . '/' . $migrationFile;
        $className = $this->getClassName($migrationFile);
        /**
         * Instanciate migration class
         */
        $class = new $className();
        /**
         * Receives SQL query from migration UP method
         */
        $sql = $class->up();
        /**
         * Execute SQL query
         */
        echo "Processing... $migrationFile\n";
        $stmt = $this->query($sql);
        /**
         * If something is wrong, 
         * @throws \Exception
         */
        $this->checkErrors($stmt);
        /**
         * If it worked, shows a message
         */
        echo "$migrationFile migrated \n\n";
    }

    /**
     * @param \PDOStatement $stmt
     * @throws Exception
     */
    private function checkErrors(\PDOStatement $stmt) : void
    {
        if ($stmt->errorCode() != 0000) {
            $errorMessage = PHP_EOL . $stmt->errorInfo()[2] . PHP_EOL;
            throw new \Exception($errorMessage);
        }
    }

    /**
     * @param string $migrationFile
     * @return string
     */
    private function getClassName(string $migrationFile) : string
    {
        /**
         * Remove .php extension
         * @var string
         */
        $withoutExtension = str_replace('.php', '', $migrationFile);
        /**
         * Remove dateTime string from migration filename
         * @var string
         */
        $withoutDate = preg_replace('/\d+./', '', $withoutExtension);
        /**
         * Returns correspondent class name to migration
         * @var string
         */
        return $this->transformToPascal(implode('_', explode('_', $withoutDate)));
    }

    /**
     * @param string $migration_name
     * @param mixed ...$columns
     * @return bool
     * @throws Exception
     */
    public function generateMigration(string $migration_name, ...$columns)
    {
        /**
         * If <action>_<table_name>_table convention isn't be
         * followed,
         * @throws Exception
         */
        if (!preg_match('/_table/', $migration_name)) {
            throw new Exception("You must follow Rino's migration name convention.");
        }
        /**
         * Transform migration_name to MigratioName case pattern
         * @var string
         */
        $MigrationName = $this->transformToPascal($migration_name);
        /**
         * Receives the table name from the migration_name (snake case)
         * @var string
         */
        $table_name = $this->parseMigrationName($migration_name);
        /**
         * Replace the table & migration names
         * @var string
         */
        $fileWithoutColumns = $this->replacements($MigrationName, $table_name, ...$columns);
        /**
         * If was passed just the migration argument...
         */
        if ($columns == null) {
            /**
             * Generate the migration file at the
             * path passed on the class constructor
             */
            $this->generate(str_replace('$columns', '', $fileWithoutColumns), $migration_name);
            return true;
        }
        $fileWithColumns = str_replace(
            '$columns', 
            $this->querifyColumns(...$columns) . $this->foreignStuff, 
            $fileWithoutColumns
        );
        $this->generate($fileWithColumns, $migration_name);
    }

    /**
     * @param mixed ...$columns
     * @return string
     */
    private function querifyColumns(...$columns) : string
    {
        $finalQuery = '';
        /**
         * Parses received columns and rewrite them to SQL syntax
         */
        foreach ($columns as $key => $column) {
            if ($key == 0) {
                $finalQuery .= $this->rewriteColumn($column);
            } else {
                $finalQuery .= ",\n\t\t\t\t" . $this->rewriteColumn($column);
            }
        }
        return $finalQuery;
    }

    /**
     * @param string $column
     * @return string
     */
    public function rewriteColumn(string $column) : string
    {
        /**
         * Replace { } to ( )
         * @var string
         */
        $replaceKeys = str_replace('}', ')', str_replace('{', '(', $column));
        /**
         * Replace : to ' '
         * @var string
         */
        $separateTypes = str_replace(':', ' ', $replaceKeys);
        /**
         * If have 'increments' statement, replace with auto_increment primary key
         * @var string
         */
        $addIncrements = str_replace('increments', 'auto_increment primary key', $separateTypes);
        /**
         * Replace ~ to ' '
         * @var string
         */
        $diffSpaces = str_replace('~', ' ', $addIncrements);
        /**
         * Configuring 'nullable' settings
         * (by default, all columns will be 'not null')
         */
        $diffSpaces .= ' not null';
        if (preg_match('/nullable/', $diffSpaces)) {
            $diffSpaces = str_replace('not null', '', $diffSpaces);
        }
        $diffSpaces = str_replace('nullable', '', $diffSpaces);
        /**
         * If has <name>_id, it's a foreign key from 'names'
         */
        if (preg_match('/\w+_id/', $diffSpaces, $foreign)) {
            /**
             * Then it's parsed and add to $this->foreignStuff
             * to be concatenated later
             * @var string
             */
            $foreignTable = str_replace('_id', '', $foreign[0]) . 's';
            $this->foreignStuff .= ",\n\t\t\t\tforeign key ($foreign[0]) references $foreignTable(id)";
        }
        return $diffSpaces;
    }

    /**
     * @param string $fileContent
     * @param string $migration_name
     */
    private function generate(string $fileContent, string $migration_name) : void
    {
        $newMigrationFilename = $this->migrationsPath . '/' . $this->getDate() . '_' .  $migration_name . '.php'; 
        file_put_contents(
            $newMigrationFilename, 
            $fileContent
        );
        echo "Migration created at: $newMigrationFilename.\n";
    }

    /**
     * @return string
     */
    private function getDate() : string
    {
        /**
         * Returns datetime string to concat with '_migration_name.php'
         * @var string
         */
        return (new DateTime())->format('YmdHis');
    }

    /**
     * @param string $MigrationName
     * @param string $table_name
     * @param mixed ...$columns
     * @return string
     */
    private function replacements(string $MigrationName, string $table_name, ...$columns) : string
    {
        /**
         * Replace the PascalCase migration name (classname)
         * @var string
         */
        $fileWithTheMigrationName = str_replace('$MigrationName', $MigrationName, $this->example); 
        /**
         * Return the table name on snake_case to overwrite example too
         * @var string
         */
        $fileWithTheTableName = str_replace('$table_name', $table_name, $fileWithTheMigrationName);
        return $fileWithTheTableName;
    }

    /**
     * @param string $migration_name
     * @return string
     */
    private function parseMigrationName(string $migration_name) : string
    {
        /**
         * Spli string on '_'
         * @var array
         */
        $command = explode('_', $migration_name);
        /**
         * Count the create_some_table parts
         * exploded
         * @var int
         */
        $commandLength = count($command);
        /**
         * Capture the operation
         * @var string
         */
        $operation = $command[0];
        /**
         * Remove 'table' from the end
         * @var bool
         */
        unset($command[$commandLength - 1]);
        /**
         * Remove the first word (SQL command)
         * and returns the name_of_table
         * @var string
         */
        return implode('_', array_slice($command, 1));
    }

    /**
     * @param string $snake_case_string
     * @return string
     */
    private function transformToPascal(string $snake_case_string) : string
    {
        /**
         * Split the snake_case_string
         * to transform the words separated
         * @var array
         */
        $words = explode('_', $snake_case_string);
        /**
         * Create a null string outside the loop
         */
        $PascalCase = '';
        /**
         * Iterate the $words array
         */
        foreach ($words as $word) {
            /**
             * Capitalize one by one
             * @var string
             */
            $PascalCase .= ucfirst($word);
        }
        /**
         * Return final result
         * @var string
         */
        return $PascalCase;
    }

}