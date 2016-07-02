<?php

namespace Odan\Migration\Adapter\Database;

//use Exception;
use PDO;
use Odan\Migration\Adapter\Database\DatabaseAdapterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * MySqlAdapter
 */
class MySqlAdapter implements DatabaseAdapterInterface
{

    /**
     *
     * @var PDO
     */
    protected $pdo;

    /**
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     *
     * @var string
     */
    protected $dbName;

    public function __construct(PDO $pdo, OutputInterface $output)
    {
        $this->pdo = $pdo;
        $this->dbName = $this->getDbName();
        $this->output = $output;
        $this->output->writeln(sprintf('Database: <info>%s</>', $this->dbName));
    }

    /**
     * getCurrentSchema
     *
     * @return array
     */
    public function getSchema()
    {
        $this->output->writeln('Load current database schema.');
        $result = array();

        $result['database'] = $this->getDatabaseSchemata($this->dbName);

        $tables = $this->getTables();
        foreach ($tables as $table) {
            $tableName = $table['table_name'];
            $this->output->writeln(sprintf('Table: <info>%s</>', $tableName));
            $result['tables'][$tableName]['table'] = $table;
            $result['tables'][$tableName]['columns'] = $this->getColumns($tableName);
            $result['tables'][$tableName]['indexes'] = $this->getIndexes($tableName);
            $result['tables'][$tableName]['foreign_keys'] = $this->getForeignKeys($tableName);
            //$result['tables'][$tableName]['create_table'] = $this->getTableCreateSql($tableName);
        }
        return $result;
    }

    /**
     * Get current database name
     *
     * @return string
     */
    public function getDbName()
    {
        return $this->pdo->query('select database()')->fetchColumn();
    }

    public function getDatabaseSchemata($dbName)
    {
        $sql = "SELECT
            default_character_set_name,
            default_collation_name
            FROM information_schema.SCHEMATA
            WHERE schema_name = %s;";
        $sql = sprintf($sql, $this->quote($dbName));
        $row = $this->pdo->query($sql)->fetch();
        return $row;
    }

    /**
     * getTables
     *
     * @return array
     */
    public function getTables()
    {
        $result = array();
        $sql = "SELECT *
            FROM
                information_schema.tables AS t,
                information_schema.collation_character_set_applicability AS ccsa
            WHERE
                ccsa.collation_name = t.table_collation
                AND t.table_schema=database()
                AND t.table_type = 'BASE TABLE'";

        $rows = $this->pdo->query($sql)->fetchAll();
        //$rows = $this->db->query('SHOW TABLES')->fetchAll();
        foreach ($rows as $row) {
            $result[] = [
                'table_name' => $row['table_name'],
                'engine' => $row['engine'],
                'table_comment' => $row['table_comment'],
                'table_collation' => $row['table_collation'],
                'character_set_name' => $row['character_set_name'],
            ];
        }
        return $result;
    }

    /**
     *
     * @param type $tableName
     * @return type
     */
    public function getColumns($tableName)
    {
        $sql = sprintf("SELECT * FROM information_schema.columns
                    WHERE table_schema=database()
                    AND table_name = %s", $this->quote($tableName));
        $rows = $this->pdo->query($sql)->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $name = $row['column_name'];
            $result[$name] = $row;
        }
        return $result;
    }

    /**
     *
     * @param type $tableName
     * @return type
     */
    public function getIndexes($tableName)
    {
        $sql = sprintf('SHOW INDEX FROM %s', $this->ident($tableName));
        $rows = $this->pdo->query($sql)->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $name = $row['key_name'];
            $result[$name] = $row;
        }
        return $result;
    }

    public function getForeignKeys($tableName)
    {
        /* $rows = $this->db
          ->from('information_schema.table_constraints')
          ->where('constraint_schema', $this->dbName)
          ->where('table_name', $tableName)
          ->where('constraint_name <> ?', 'PRIMARY')
          ->fetchAll();

          $result = [];
          foreach ($rows as $row) {
          $name = $row['constraint_name'];
          $result[$name] = $row;
          }
          return $result; */

        $sql = sprintf("SELECT
                cols.TABLE_NAME,
                cols.COLUMN_NAME,
                cRefs.CONSTRAINT_NAME,
                refs.REFERENCED_TABLE_NAME,
                refs.REFERENCED_COLUMN_NAME,
                cRefs.UPDATE_RULE,
                cRefs.DELETE_RULE
            FROM INFORMATION_SCHEMA.COLUMNS as cols
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
        $result = $stm->fetchAll();
        return $result;
    }

    /**
     *
     * @param type $tableName
     * @return type
     */
    public function getTableCreateSql($tableName)
    {
        $sql = sprintf('SHOW CREATE TABLE %s', $this->ident($tableName));
        $result = $this->pdo->query($sql)->fetch();
        return $result['create table'];
    }

    /**
     * Escape identifier (column, table) with backtick
     *
     * @see: http://dev.mysql.com/doc/refman/5.0/en/identifiers.html
     *
     * @param string $value
     * @param string $quote
     * @return string identifier escaped string
     */
    public function ident($value, $quote = "`")
    {
        $value = preg_replace('/[^A-Za-z0-9_]+/', '', $value);
        if (strpos($value, '.') !== false) {
            $values = explode('.', $value);
            $value = $quote . implode($quote . '.' . $quote, $values) . $quote;
        } else {
            $value = $quote . $value . $quote;
        }
        return $value;
    }

    public function esc($value)
    {
        if ($value === null) {
            return 'NULL';
        }
        $value = substr($this->pdo->quote($value), 1, -1);
        return $value;
    }

    public function quote($value)
    {
        if ($value === null) {
            return 'NULL';
        }
        return $this->pdo->quote($value);
    }
}
