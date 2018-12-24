<?php

namespace Odan\Migration\Adapter\Database;

use Odan\Migration\Utility\ArrayUtil;
use PDO;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * MySqlAdapter.
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
        return $this->pdo->query('select database()')->fetchColumn();
    }

    /**
     * Load current database schema.
     *
     * @return array
     */
    public function getSchema(): array
    {
        $this->output->writeln('Load current database schema.');

        $result = [];

        $result['database'] = $this->getDatabaseSchemata($this->dbName);

        $tables = $this->getTables();

        foreach ($tables as $table) {
            $tableName = $table['table_name'];
            $this->output->writeln(sprintf('Table: <info>%s</info>', $tableName));
            $result['tables'][$tableName]['table'] = $table;
            $result['tables'][$tableName]['columns'] = $this->getColumns($tableName);
            $result['tables'][$tableName]['indexes'] = $this->getIndexes($tableName);
            $result['tables'][$tableName]['foreign_keys'] = $this->getForeignKeys($tableName);
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
        $row = $this->pdo->query($sql)->fetch();

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
     * Get all tables.
     *
     * @return array
     */
    protected function getTables(): array
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

        $rows = $this->pdo->query($sql)->fetchAll();

        foreach ($rows as $row) {
            $result[] = [
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
        $sql = sprintf('SELECT * FROM information_schema.columns
                    WHERE table_schema=database()
                    AND table_name = %s', $this->quote($tableName));

        $rows = $this->pdo->query($sql)->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $name = $row['COLUMN_NAME'];
            $result[$name] = $row;
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
        $sql = sprintf('SHOW INDEX FROM %s', $this->ident($tableName));
        $rows = $this->pdo->query($sql)->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            if (isset($row['Cardinality'])) {
                unset($row['Cardinality']);
            }
            $name = $row['Key_name'];
            $seq = $row['Seq_in_index'];
            $result[$name][$seq] = $row;
        }

        return $result;
    }

    /**
     * Escape identifier (column, table) with backtick.
     *
     * @see: http://dev.mysql.com/doc/refman/5.0/en/identifiers.html
     *
     * @param string $value
     *
     * @return string identifier escaped string
     */
    public function ident(string $value): string
    {
        $quote = '`';
        $value = preg_replace('/[^A-Za-z0-9_\.]+/', '', $value);

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
                cols.TABLE_NAME = %s
                AND cols.TABLE_SCHEMA = DATABASE()
                AND refs.REFERENCED_TABLE_NAME IS NOT NULL
                AND cons.CONSTRAINT_TYPE = 'FOREIGN KEY'
            ;", $this->quote($tableName));
        $stm = $this->pdo->query($sql);
        $rows = $stm->fetchAll();

        if (empty($rows)) {
            return null;
        }

        $result = [];
        foreach ($rows as $row) {
            $result[$row['CONSTRAINT_NAME']] = $row;
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
