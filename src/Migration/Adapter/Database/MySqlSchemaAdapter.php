<?php

namespace Odan\Migration\Adapter\Database;

use Odan\Migration\Utility\ArrayUtil;
use PDO;
use PDOStatement;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * MySqlSchemaAdapter.
 */
class MySqlSchemaAdapter implements SchemaAdapterInterface
{
    /**
     * PDO.
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * Console Output Interface.
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * Current database name.
     *
     * @var string
     */
    protected $dbName;

    /**
     * Constructor.
     *
     * @param PDO $pdo
     * @param OutputInterface $output
     */
    public function __construct(PDO $pdo, OutputInterface $output)
    {
        $this->pdo = $pdo;
        $this->dbName = $this->getDbName();
        $this->output = $output;
        $this->output->writeln(sprintf('Database: <info>%s</info>', $this->dbName));
    }

    /**
     * Get current database name.
     *
     * @return string
     */
    protected function getDbName(): string
    {
        return (string)$this->createQueryStatement('select database()')->fetchColumn();
    }

    /**
     * Create a new PDO statement.
     *
     * @param string $sql The sql
     *
     * @return PDOStatement The statement
     */
    protected function createQueryStatement(string $sql): PDOStatement
    {
        $statement = $this->pdo->query($sql);

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Invalid statement');
        }

        return $statement;
    }

    /**
     * Fetch all rows as array.
     *
     * @param string $sql The sql
     *
     * @return array The rows
     */
    protected function queryFetchAll(string $sql): array
    {
        $statement = $this->createQueryStatement($sql);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return [];
        }

        return $rows;
    }

    /**
     * Load current database schema.
     *
     * @param array|null $tableNames
     *
     * @return array
     */
    public function getSchema($tableNames = null): array
    {
        $this->output->writeln('Load current database schema.');

        $result = [];

        $result['database'] = $this->getDatabaseSchemata($this->dbName);

        // processing by chunks for better speed when we have hundreds of tables
        $tables = $this->getTables($tableNames);

        $tableNameChunks = array_chunk(array_column($tables, 'table_name'), 300);
        foreach ($tableNameChunks as $tablesInChunk) {
            $columns = $this->getColumnHash($tablesInChunk);
            $indexes = $this->getIndexHash($tablesInChunk);
            $foreign_keys = $this->getForeignKeysHash($tablesInChunk);
            foreach ($tablesInChunk as $tableName) {
                $this->output->writeln(sprintf('Table: <info>%s</info>', $tableName), OutputInterface::VERBOSITY_VERBOSE);
                $result['tables'][$tableName]['table'] = $tables[$tableName];
                $result['tables'][$tableName]['columns'] = $columns[$tableName] ?? [];
                $result['tables'][$tableName]['indexes'] = $indexes[$tableName] ?? [];
                $result['tables'][$tableName]['foreign_keys'] = $foreign_keys[$tableName] ?? null;
            }
        }

        $array = new ArrayUtil();
        $array->unsetArrayKeys($result, 'TABLE_SCHEMA');

        return $result;
    }

    /**
     * Get database schemata.
     *
     * @param string $dbName
     *
     * @return array
     */
    protected function getDatabaseSchemata(string $dbName): array
    {
        $sql = 'SELECT
            default_character_set_name,
            default_collation_name
            FROM information_schema.SCHEMATA
            WHERE schema_name = %s;';
        $sql = sprintf($sql, $this->quote($dbName));
        $row = $this->createQueryStatement($sql)->fetch();

        return $row;
    }

    /**
     * Quote value.
     *
     * @param string|null $value
     *
     * @return string
     */
    public function quote(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return $this->pdo->quote($value);
    }

    /**
     * Quote array of values.
     *
     * @param array|string|null $values
     *
     * @return string[]
     */
    public function quoteArray($values)
    {
        return array_map(function ($value) {
            return $this->quote($value);
        }, (array)$values);
    }

    /**
     * Get all tables.
     *
     * @param array|null $tableNames
     *
     * @return array
     */
    protected function getTables($tableNames = null): array
    {
        $result = [];
        $sql = "SELECT *
            FROM
                information_schema.tables AS t,
                information_schema.collation_character_set_applicability AS ccsa
            WHERE
                ccsa.collation_name = t.table_collation
                AND t.table_schema=database()
                AND t.table_type = 'BASE TABLE'";

        if ($tableNames !== null) {
            if (empty($tableNames)) {
                return [];
            }
            $quotedNames = $this->quoteArray($tableNames);
            $sql .= ' AND t.table_name in (' . implode(',', $quotedNames) . ')';
        }

        $rows = $this->queryFetchAll($sql);

        foreach ($rows as $row) {
            $result[$row['TABLE_NAME']] = [
                'table_name' => $row['TABLE_NAME'],
                'engine' => $row['ENGINE'],
                'table_comment' => $row['TABLE_COMMENT'],
                'table_collation' => $row['TABLE_COLLATION'],
                'character_set_name' => $row['CHARACTER_SET_NAME'],
                'row_format' => $row['ROW_FORMAT'],
            ];
        }

        return $result;
    }

    /**
     * Get table columns.
     *
     * @param string $tableName
     *
     * @return array
     */
    protected function getColumns($tableName): array
    {
        $rows = $this->getColumnHash([$tableName]);

        return $rows[$tableName] ?? [];
    }

    /**
     * Get columns, grouped by table name.
     *
     * @param array $tableNames
     *
     * @return array[]
     */
    protected function getColumnHash(array $tableNames)
    {
        if (empty($tableNames)) {
            return [];
        }

        $quotedNames = $this->quoteArray($tableNames);
        $sql = sprintf('SELECT * FROM information_schema.columns
                    WHERE table_schema=database()
                    AND table_name in (%s)', implode(',', $quotedNames));

        $rows = $this->queryFetchAll($sql);

        $result = [];
        foreach ($rows as $row) {
            $table_name = $row['TABLE_NAME'];
            $column_name = $row['COLUMN_NAME'];
            $result[$table_name][$column_name] = $row;
        }

        return $result;
    }

    /**
     * Get indexes.
     *
     * @param string $tableName
     *
     * @return array
     */
    protected function getIndexes($tableName): array
    {
        $rows = $this->getIndexHash([$tableName]);

        return $rows[$tableName] ?? [];
    }

    /**
     * Get indexes, grouped by table name.
     *
     * @param array $tableNames
     *
     * @return array
     */
    protected function getIndexHash($tableNames)
    {
        if (empty($tableNames)) {
            return [];
        }

        $quotedNames = $this->quoteArray($tableNames);
        $sql = sprintf("SELECT 
                `TABLE_NAME` as 'Table',
                `NON_UNIQUE` as 'Non_unique',
                `INDEX_NAME` as 'Key_name',
                `SEQ_IN_INDEX` as 'Seq_in_index',
                `COLUMN_NAME` as 'Column_name',
                `COLLATION` as 'Collation',
                `SUB_PART` as 'Sub_part',
                `PACKED` as 'Packed',
                `NULLABLE` as 'Null',
                `INDEX_TYPE` as 'Index_type',
                `COMMENT` as 'Comment',
                `INDEX_COMMENT` as 'Index_comment'
                FROM information_schema.statistics
                    WHERE table_schema=database()
                    AND table_name in (%s)", implode(',', $quotedNames));
        $rows = $this->queryFetchAll($sql);
        $result = [];
        foreach ($rows as $row) {
            $tableName = $row['Table'];
            $name = $row['Key_name'];
            $seq = $row['Seq_in_index'];
            $result[$tableName][$name][$seq] = $row;
        }

        return $result;
    }

    /**
     * Escape identifier (column, table) with backtick.
     *
     * @see: http://dev.mysql.com/doc/refman/5.0/en/identifiers.html
     *
     * @param string $value
     * @param string $quote
     *
     * @return string identifier escaped string
     */
    public function ident(string $value, $quote = '`'): string
    {
        $value = preg_replace('/[^A-Za-z0-9_\.]+/', '', $value);
        $value = is_scalar($value) ? (string)$value : '';

        if (strpos($value, '.') !== false) {
            $values = explode('.', $value);
            $value = $quote . implode($quote . '.' . $quote, $values) . $quote;
        } else {
            $value = $quote . $value . $quote;
        }

        return $value;
    }

    /**
     * Get foreign keys.
     *
     * @param string $tableName
     *
     * @return array|null
     */
    protected function getForeignKeys(string $tableName): ?array
    {
        $rows = $this->getForeignKeysHash([$tableName]);

        return $rows[$tableName] ?? [];
    }

    /**
     * Get foreign keys, grouped by table name.
     *
     * @param array $tableNames
     *
     * @return array|null
     */
    protected function getForeignKeysHash($tableNames)
    {
        if (empty($tableNames)) {
            return [];
        }

        $quotedNames = $this->quoteArray($tableNames);
        $sql = sprintf("SELECT
                cols.TABLE_NAME,
                cols.COLUMN_NAME,
                cRefs.CONSTRAINT_NAME,
                refs.REFERENCED_TABLE_NAME,
                refs.REFERENCED_COLUMN_NAME,
                cRefs.UPDATE_RULE,
                cRefs.DELETE_RULE
            FROM INFORMATION_SCHEMA.COLUMNS AS cols
            LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE AS refs
                ON refs.TABLE_SCHEMA=cols.TABLE_SCHEMA
                AND refs.REFERENCED_TABLE_SCHEMA=cols.TABLE_SCHEMA
                AND refs.TABLE_NAME=cols.TABLE_NAME
                AND refs.COLUMN_NAME=cols.COLUMN_NAME
            LEFT JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS AS cons
                ON cons.TABLE_SCHEMA=cols.TABLE_SCHEMA
                AND cons.TABLE_NAME=cols.TABLE_NAME
                AND cons.CONSTRAINT_NAME=refs.CONSTRAINT_NAME
            LEFT JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS AS cRefs
                ON cRefs.CONSTRAINT_SCHEMA=cols.TABLE_SCHEMA
                AND cRefs.CONSTRAINT_NAME=refs.CONSTRAINT_NAME
            WHERE
                cols.TABLE_NAME in (%s)
                AND cols.TABLE_SCHEMA = DATABASE()
                AND refs.REFERENCED_TABLE_NAME IS NOT NULL
                AND cons.CONSTRAINT_TYPE = 'FOREIGN KEY'
            ;", implode(',', $quotedNames));

        $rows = $this->queryFetchAll($sql);

        if (empty($rows)) {
            return null;
        }

        $result = [];
        foreach ($rows as $row) {
            $result[$row['TABLE_NAME']][$row['CONSTRAINT_NAME']] = $row;
        }

        return $result;
    }

    /**
     * Escape value.
     *
     * @param string|null $value
     *
     * @return string
     */
    public function esc(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $value = substr($this->pdo->quote($value), 1, -1);

        return $value;
    }
}
