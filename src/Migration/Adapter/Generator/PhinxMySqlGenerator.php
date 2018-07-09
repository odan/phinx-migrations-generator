<?php

namespace Odan\Migration\Adapter\Generator;

use Odan\Migration\Adapter\Database\MySqlAdapter;
use Phinx\Db\Adapter\AdapterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * PhinxMySqlGenerator.
 */
class PhinxMySqlGenerator
{
    /**
     * Database adapter.
     *
     * @var MySqlAdapter
     */
    protected $dba;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * Options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * PSR-2: All PHP files MUST use the Unix LF (linefeed) line ending.
     *
     * @var string
     */
    protected $nl = "\n";

    /**
     * @var string
     */
    protected $ind = '    ';

    /**
     * @var string
     */
    protected $ind2 = '        ';

    /**
     * @var string
     */
    protected $ind3 = '            ';

    /**
     * Constructor.
     *
     * @param MySqlAdapter $dba
     * @param OutputInterface $output
     * @param mixed $options Options
     */
    public function __construct(MySqlAdapter $dba, OutputInterface $output, $options = [])
    {
        $this->dba = $dba;
        $this->output = $output;

        $default = [
            // Experimental foreign key support.
            'foreign_keys' => false,
            // Default migration table name
            'default_migration_table' => 'phinxlog',
        ];
        $this->options = array_replace_recursive($default, $options);
    }

    /**
     * Create migration.
     *
     * @param string $name Name of the migration
     * @param array $newSchema
     * @param array $oldSchema
     *
     * @return string PHP code
     */
    public function createMigration($name, $newSchema, $oldSchema)
    {
        $output = [];
        $output[] = '<?php';
        $output[] = '';
        $output[] = 'use Phinx\Migration\AbstractMigration;';
        $output[] = 'use Phinx\Db\Adapter\MysqlAdapter;';
        $output[] = '';
        $output[] = sprintf('class %s extends AbstractMigration', $name);
        $output[] = '{';
        $output = $this->addChangeMethod($output, $newSchema, $oldSchema);
        $output[] = '}';
        $output[] = '';
        $result = implode($this->nl, $output);

        return $result;
    }

    /**
     * Generate code for change function.
     *
     * @param string[] $output Output
     * @param array $new New schema
     * @param array $old Old schema
     *
     * @return string[] Output
     */
    public function addChangeMethod($output, $new, $old)
    {
        $output[] = $this->ind . 'public function change()';
        $output[] = $this->ind . '{';
        $output = $this->getTableMigration($output, $new, $old);
        $output[] = $this->ind . '}';

        return $output;
    }

    /**
     * Get table migration.
     *
     * @param string[] $output Output
     * @param array $new New schema
     * @param array $old Old schema
     *
     * @return array Output
     */
    public function getTableMigration($output, $new, $old)
    {
        if (!empty($this->options['foreign_keys'])) {
            $output[] = $this->getSetUniqueChecks(0);
            $output[] = $this->getSetForeignKeyCheck(0);
        }

        $output = $this->getTableMigrationNewDatabase($output, $new, $old);
        $output = $this->getTableMigrationNewTables($output, $new, $old);

        if (!empty($this->options['foreign_keys'])) {
            $lines = $this->getForeignKeysMigrations($new, $old);
            $output = $this->appendLines($output, $lines);
        }

        $output = $this->getTableMigrationOldTables($output, $new, $old);

        if (!empty($this->options['foreign_keys'])) {
            $output[] = $this->getSetForeignKeyCheck(1);
            $output[] = $this->getSetUniqueChecks(1);
        }

        return $output;
    }

    /**
     * Generate Set Unique Checks.
     *
     * @param int $value 0 or 1
     *
     * @return string
     */
    protected function getSetUniqueChecks($value)
    {
        return sprintf('%s$this->execute("SET UNIQUE_CHECKS = %s;");', $this->ind2, $value);
    }

    /**
     * Generate SetForeignKeyCheck.
     *
     * @param int $value
     *
     * @return string
     */
    protected function getSetForeignKeyCheck($value)
    {
        return sprintf('%s$this->execute("SET FOREIGN_KEY_CHECKS = %s;");', $this->ind2, $value);
    }

    /**
     * Get table migration (new database).
     *
     * @param array $output
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    protected function getTableMigrationNewDatabase($output, $new, $old)
    {
        if (empty($new['database'])) {
            return $output;
        }
        if ($this->neq($new, $old, ['database', 'default_character_set_name'])) {
            $output[] = $this->getAlterDatabaseCharset($new['database']['default_character_set_name']);
        }
        if ($this->neq($new, $old, ['database', 'default_collation_name'])) {
            $output[] = $this->getAlterDatabaseCollate($new['database']['default_collation_name']);
        }

        return $output;
    }

    /**
     * Compare array (not).
     *
     * @param array $arr
     * @param array $arr2
     * @param array $keys
     *
     * @return bool
     */
    protected function neq($arr, $arr2, $keys)
    {
        return !$this->eq($arr, $arr2, $keys);
    }

    /**
     * Compare array.
     *
     * @param array $arr
     * @param array $arr2
     * @param array $keys
     *
     * @return bool
     */
    protected function eq($arr, $arr2, $keys)
    {
        $val1 = $this->find($arr, $keys);
        $val2 = $this->find($arr2, $keys);

        return $val1 === $val2;
    }

    /**
     * Get array value by keys.
     *
     * @param array $array
     * @param array $parts
     *
     * @return mixed
     */
    protected function find($array, $parts)
    {
        foreach ($parts as $part) {
            if (!array_key_exists($part, $array)) {
                return null;
            }
            $array = $array[$part];
        }

        return $array;
    }

    /**
     * Generate alter database charset.
     *
     * @param string $charset
     * @param string $database
     *
     * @return string
     */
    protected function getAlterDatabaseCharset($charset, $database = null)
    {
        if ($database !== null) {
            $database = ' ' . $this->dba->ident($database);
        }
        $charset = $this->dba->quote($charset);

        return sprintf('%s$this->execute("ALTER DATABASE%s CHARACTER SET %s;");', $this->ind2, $database, $charset);
    }

    /**
     * Generate alter database collate.
     *
     * @param string $collate
     * @param string $database
     *
     * @return string
     */
    protected function getAlterDatabaseCollate($collate, $database = null)
    {
        if ($database) {
            $database = ' ' . $this->dba->ident($database);
        }
        $collate = $this->dba->quote($collate);

        return sprintf('%s$this->execute("ALTER DATABASE%s COLLATE=%s;");', $this->ind2, $database, $collate);
    }

    /**
     * Get table migration (new tables).
     *
     * @param array $output
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    protected function getTableMigrationNewTables($output, $new, $old)
    {
        if (empty($new['tables'])) {
            return $output;
        }
        foreach ($new['tables'] as $tableName => $table) {
            if ($tableName == $this->options['default_migration_table']) {
                continue;
            }

            if (!isset($old['tables'][$tableName])) {
                // create the table
                $table['has_table_variable'] = true;
                $output = $this->getCreateTable($output, $table, $tableName);
            }

            $output = $this->getTableMigrationNewTablesColumns($output, $table, $tableName, $new, $old);
            $output = $this->getTableMigrationOldTablesColumns($output, $tableName, $new, $old);
            $output = $this->getTableMigrationIndexes($output, $table, $tableName, $new, $old);
        }

        return $output;
    }

    /**
     * Generate create table.
     *
     * @param array $output
     * @param array $table
     * @param string $tableName
     *
     * @return array
     */
    protected function getCreateTable($output, $table, $tableName)
    {
        $output[] = $this->getTableVariable($table, $tableName);

        return $output;
    }

    /**
     * Generate create table variable.
     *
     * @param array $table
     * @param string $tableName
     *
     * @return string
     */
    protected function getTableVariable($table, $tableName)
    {
        $options = $this->getTableOptions($table);
        $result = sprintf('%s$table = $this->table("%s", %s);', $this->ind2, $tableName, $options);

        return $result;
    }

    /**
     * Generate create table variable.
     *
     * @param string $tableName
     *
     * @return string
     */
    protected function getTableWithoutOptionsVariable($tableName)
    {
        return sprintf('%s$table = $this->table("%s");', $this->ind2, $tableName);
    }

    /**
     * Get table options.
     *
     * @param array $table
     *
     * @return string
     */
    protected function getTableOptions($table)
    {
        $attributes = [];

        $attributes = $this->getPhinxTablePrimaryKey($attributes, $table);

        // collation
        $attributes = $this->getPhinxTableEngine($attributes, $table);

        // encoding
        $attributes = $this->getPhinxTableEncoding($attributes, $table);

        // collation
        $attributes = $this->getPhinxTableCollation($attributes, $table);

        // comment
        $attributes = $this->getPhinxTableComment($attributes, $table);

        // row_format
        $attributes = $this->getPhinxTableRowFormat($attributes, $table);

        $result = '[' . implode(', ', $attributes) . ']';

        return $result;
    }

    /**
     * Define table id value.
     *
     * @param array $attributes
     * @param array $table
     *
     * @return array Attributes
     */
    protected function getPhinxTablePrimaryKey($attributes, $table)
    {
        $primaryKeys = $this->getPrimaryKeys($table);
        $attributes[] = "'id' => false";

        if (!empty($primaryKeys)) {
            $valueString = '[' . implode(', ', $primaryKeys) . ']';
            $attributes[] = "'primary_key' => " . $valueString;
        }

        return $attributes;
    }

    /**
     * Collect alternate primary keys.
     *
     * @param array $table
     *
     * @return array
     */
    protected function getPrimaryKeys($table)
    {
        $primaryKeys = [];
        foreach ($table['columns'] as $column) {
            $columnName = $column['COLUMN_NAME'];
            $columnKey = $column['COLUMN_KEY'];
            if ($columnKey !== 'PRI') {
                continue;
            }
            $primaryKeys[] = '"' . $columnName . '"';
        }

        return $primaryKeys;
    }

    /**
     * Define table engine (defaults to InnoDB).
     *
     * @param array $attributes
     * @param array $table
     *
     * @return array Attributes
     */
    protected function getPhinxTableEngine($attributes, $table)
    {
        if (!empty($table['table']['engine'])) {
            $attributes[] = '\'engine\' => "' . addslashes($table['table']['engine']) . '"';
        } else {
            $attributes[] = '\'engine\' => "InnoDB"';
        }

        return $attributes;
    }

    /**
     * Define table character set (defaults to utf8).
     *
     * @param array $attributes
     * @param array $table
     *
     * @return array Attributes
     */
    protected function getPhinxTableEncoding($attributes, $table)
    {
        if (!empty($table['table']['character_set_name'])) {
            $attributes[] = '\'encoding\' => "' . addslashes($table['table']['character_set_name']) . '"';
        } else {
            $attributes[] = '\'encoding\' => "utf8"';
        }

        return $attributes;
    }

    /**
     * Define table collation (defaults to `utf8_general_ci`).
     *
     * @param array $attributes
     * @param array $table
     *
     * @return array Attributes
     */
    protected function getPhinxTableCollation($attributes, $table)
    {
        if (!empty($table['table']['table_collation'])) {
            $attributes[] = '\'collation\' => "' . addslashes($table['table']['table_collation']) . '"';
        } else {
            $attributes[] = '\'collation\' => "utf8_general_ci"';
        }

        return $attributes;
    }

    /**
     * Set a text comment on the table.
     *
     * @param array $attributes
     * @param array $table
     *
     * @return array Attributes
     */
    protected function getPhinxTableComment($attributes, $table)
    {
        if (!empty($table['table']['table_comment'])) {
            $attributes[] = '\'comment\' => "' . addslashes($table['table']['table_comment']) . '"';
        } else {
            $attributes[] = '\'comment\' => ""';
        }

        return $attributes;
    }

    /**
     * Get table for format.
     *
     * @param array $attributes
     * @param array $table
     *
     * @return array Attributes
     */
    protected function getPhinxTableRowFormat($attributes, $table)
    {
        if (!empty($table['table']['row_format'])) {
            $attributes[] = '\'row_format\' => "' . addslashes($table['table']['row_format']) . '"';
        }

        return $attributes;
    }

    /**
     * Get table migration (new table columns).
     *
     * @param array $output
     * @param array $table
     * @param string $tableName
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    protected function getTableMigrationNewTablesColumns($output, $table, $tableName, $new, $old)
    {
        if (empty($table['columns'])) {
            return $output;
        }
        $hasTableVariable = !empty($table['has_table_variable']);
        $opened = false;

        foreach ($table['columns'] as $columnName => $columnData) {
            if (!isset($old['tables'][$tableName]['columns'][$columnName])) {
                $opened = true;

                if (!$hasTableVariable) {
                    $output[] = sprintf('%s$table = $this->table("%s");', $this->ind2, $tableName);
                    $hasTableVariable = true;
                }

                $output[] = $this->getColumnCreateAddNoUpdate($new, $tableName, $columnName);
            } else {
                if ($this->neq($new, $old, ['tables', $tableName, 'columns', $columnName])) {
                    $output[] = $this->getColumnUpdate($new, $tableName, $columnName);
                }
            }
        }
        if ($opened) {
            $output[] = sprintf('%s$table->save();', $this->ind2);
        }

        return $output;
    }

    /**
     * Generate column create.
     *
     * @param array $schema
     * @param string $table
     * @param string $columnName
     *
     * @return string[]
     */
    protected function getColumnCreate($schema, $table, $columnName)
    {
        $columns = $schema['tables'][$table]['columns'];
        $columnData = $columns[$columnName];
        $phinxType = $this->getPhinxColumnType($columnData);
        $columnAttributes = $this->getPhinxColumnOptions($phinxType, $columnData, $columns);

        return [$table, $columnName, $phinxType, $columnAttributes];
    }

    /**
     * Map MySql data type to Phinx\Db\Adapter\AdapterInterface::PHINX_TYPE_*.
     *
     * @param array $columnData
     *
     * @return string
     */
    public function getPhinxColumnType($columnData)
    {
        $columnType = $columnData['COLUMN_TYPE'];
        if ($columnType == 'tinyint(1)') {
            return 'boolean';
        }

        $map = [
            'tinyint' => 'integer',
            'smallint' => 'integer',
            'int' => 'integer',
            'mediumint' => 'integer',
            'bigint' => 'integer',
            'tinytext' => 'text',
            'mediumtext' => 'text',
            'longtext' => 'text',
            'varchar' => 'string',
            'tinyblob' => 'blob',
            'mediumblob' => 'blob',
            'longblob' => 'blob',
        ];

        $type = $this->getMySQLColumnType($columnData);
        if (isset($map[$type])) {
            $type = $map[$type];
        }

        return $type;
    }

    /**
     * Get column type.
     *
     * @param array $columnData
     *
     * @return string
     */
    protected function getMySQLColumnType($columnData)
    {
        $type = $columnData['COLUMN_TYPE'];
        $pattern = '/^[a-z]+/';
        $match = null;
        preg_match($pattern, $type, $match);

        return $match[0];
    }

    /**
     * Generate phinx column options.
     *
     * https://media.readthedocs.org/pdf/phinx/latest/phinx.pdf
     *
     * @param string $phinxType
     * @param array $columnData
     * @param array $columns
     *
     * @return string
     */
    protected function getPhinxColumnOptions($phinxType, $columnData, $columns)
    {
        $attributes = [];

        $attributes = $this->getPhinxColumnOptionsNull($attributes, $columnData);

        // default value
        $attributes = $this->getPhinxColumnOptionsDefault($attributes, $columnData);

        // For timestamp columns:
        $attributes = $this->getPhinxColumnOptionsTimestamp($attributes, $columnData);

        // limit / length
        $attributes = $this->getPhinxColumnOptionsLimit($attributes, $columnData);

        // numeric attributes
        $attributes = $this->getPhinxColumnOptionsNumeric($attributes, $columnData);

        // enum values
        if ($phinxType === 'enum') {
            $attributes = $this->getOptionEnumValue($attributes, $columnData);
        }

        // Collation
        $attributes = $this->getPhinxColumnCollation($phinxType, $attributes, $columnData);

        // Encoding
        $attributes = $this->getPhinxColumnEncoding($phinxType, $attributes, $columnData);

        // Comment
        $attributes = $this->getPhinxColumnOptionsComment($attributes, $columnData);

        // after: specify the column that a new column should be placed after
        $attributes = $this->getPhinxColumnOptionsAfter($attributes, $columnData, $columns);

        // @todo
        // update set an action to be triggered when the row is updated (use with CURRENT_TIMESTAMP)
        //
        // For foreign key definitions:
        // update set an action to be triggered when the row is updated
        // delete set an action to be triggered when the row is deleted

        $result = '[' . implode(', ', $attributes) . ']';

        return $result;
    }

    /**
     * Generate phinx column options (null).
     *
     * @param array $attributes
     * @param array $columnData
     *
     * @return string[] Attributes
     */
    protected function getPhinxColumnOptionsNull($attributes, $columnData)
    {
        // has NULL
        if ($columnData['IS_NULLABLE'] === 'YES') {
            $attributes[] = '\'null\' => true';
        } else {
            $attributes[] = '\'null\' => false';
        }

        return $attributes;
    }

    /**
     * Generate phinx column options (default value).
     *
     * @param array $attributes
     * @param array $columnData
     *
     * @return array Attributes
     */
    protected function getPhinxColumnOptionsDefault($attributes, $columnData)
    {
        if ($columnData['COLUMN_DEFAULT'] !== null) {
            $default = is_int($columnData['COLUMN_DEFAULT']) ? $columnData['COLUMN_DEFAULT'] : '"' . $columnData['COLUMN_DEFAULT'] . '"';
            $attributes[] = '\'default\' => ' . $default;
        }

        return $attributes;
    }

    /**
     * Generate phinx column options (update).
     *
     * @param array $attributes
     * @param array $columnData
     *
     * @return array Attributes
     */
    protected function getPhinxColumnOptionsTimestamp($attributes, $columnData)
    {
        // default set default value (use with CURRENT_TIMESTAMP)
        // on update CURRENT_TIMESTAMP
        if (strpos($columnData['EXTRA'], 'on update CURRENT_TIMESTAMP') !== false) {
            $attributes[] = '\'update\' => \'CURRENT_TIMESTAMP\'';
        }

        return $attributes;
    }

    /**
     * Generate phinx column options (update).
     *
     * @param array $attributes
     * @param array $columnData
     *
     * @return array Attributes
     */
    protected function getPhinxColumnOptionsLimit($attributes, $columnData)
    {
        $limit = $this->getColumnLimit($columnData);
        if ($limit) {
            $attributes[] = '\'limit\' => ' . $limit;
        }

        return $attributes;
    }

    /**
     * Generate column limit.
     *
     * @param array $columnData
     *
     * @return string
     */
    public function getColumnLimit($columnData)
    {
        $limit = 0;
        $type = $this->getMySQLColumnType($columnData);
        switch ($type) {
            case 'int':
                $limit = 'MysqlAdapter::INT_REGULAR';
                break;
            case 'tinyint':
                $limit = 'MysqlAdapter::INT_TINY';
                break;
            case 'smallint':
                $limit = 'MysqlAdapter::INT_SMALL';
                break;
            case 'mediumint':
                $limit = 'MysqlAdapter::INT_MEDIUM';
                break;
            case 'bigint':
                $limit = 'MysqlAdapter::INT_BIG';
                break;
            case 'tinytext':
                $limit = 'MysqlAdapter::TEXT_TINY';
                break;
            case 'mediumtext':
                $limit = 'MysqlAdapter::TEXT_MEDIUM';
                break;
            case 'longtext':
                $limit = 'MysqlAdapter::TEXT_LONG';
                break;
            case 'longblob':
                $limit = 'MysqlAdapter::BLOB_LONG';
                break;
            case 'mediumblob':
                $limit = 'MysqlAdapter::BLOB_MEDIUM';
                break;
            case 'blob':
                $limit = 'MysqlAdapter::BLOB_REGULAR';
                break;
            case 'tinyblob':
                $limit = 'MysqlAdapter::BLOB_TINY';
                break;
            default:
                if (!empty($columnData['CHARACTER_MAXIMUM_LENGTH'])) {
                    $limit = $columnData['CHARACTER_MAXIMUM_LENGTH'];
                } else {
                    $pattern = '/\((\d+)\)/';
                    if (preg_match($pattern, $columnData['COLUMN_TYPE'], $match) === 1) {
                        $limit = $match[1];
                    }
                }
        }

        return $limit;
    }

    /**
     * Generate phinx column options (default value).
     *
     * @param array $attributes
     * @param array $columnData
     *
     * @return array Attributes
     */
    protected function getPhinxColumnOptionsNumeric($attributes, $columnData)
    {
        // For decimal columns
        if (!empty($columnData['NUMERIC_PRECISION'])) {
            $attributes[] = '\'precision\' => ' . $columnData['NUMERIC_PRECISION'];
        }
        if (!empty($columnData['NUMERIC_SCALE'])) {
            $attributes[] = '\'scale\' => ' . $columnData['NUMERIC_SCALE'];
        }

        // signed enable or disable the unsigned option (only applies to MySQL)
        $match = null;
        $pattern = '/\(\d+\) unsigned$/';
        if (preg_match($pattern, $columnData['COLUMN_TYPE'], $match) === 1) {
            $attributes[] = '\'signed\' => false';
        }

        // For integer and biginteger columns:
        // identity enable or disable automatic incrementing
        if ($columnData['EXTRA'] == 'auto_increment') {
            $attributes[] = '\'identity\' => \'enable\'';
        }

        return $attributes;
    }

    /**
     * Generate option enum values.
     *
     * @param array $attributes
     * @param array $columnData
     *
     * @return array Attributes
     */
    public function getOptionEnumValue($attributes, $columnData)
    {
        $match = null;
        $pattern = '/enum\((.*)\)/';
        if (preg_match($pattern, $columnData['COLUMN_TYPE'], $match) === 1) {
            $values = str_getcsv($match[1], ',', "'", '\\');
            foreach ($values as $k => $value) {
                $values[$k] = "'" . addcslashes($value, "'") . "'";
            }
            $valueList = implode(',', array_values($values));
            $arr = sprintf('[%s]', $valueList);
            $attributes[] = sprintf('\'values\' => %s', $arr);

            return $attributes;
        }

        return $attributes;
    }

    /**
     * Set collation that differs from table defaults (only applies to MySQL).
     *
     * @param string $phinxType
     * @param array $attributes
     * @param array $columnData
     *
     * @return array Attributes
     */
    protected function getPhinxColumnCollation($phinxType, $attributes, $columnData)
    {
        $allowedTypes = [
            AdapterInterface::PHINX_TYPE_CHAR,
            AdapterInterface::PHINX_TYPE_STRING,
            AdapterInterface::PHINX_TYPE_TEXT,
        ];
        if (!in_array($phinxType, $allowedTypes)) {
            return $attributes;
        }

        if (!empty($columnData['COLLATION_NAME'])) {
            $attributes[] = '\'collation\' => "' . addslashes($columnData['COLLATION_NAME']) . '"';
        }

        return $attributes;
    }

    /**
     * Set character set that differs from table defaults *(only applies to MySQL)* (only applies to MySQL).
     *
     * @param string $phinxType
     * @param array $attributes
     * @param array $columnData
     *
     * @return array Attributes
     */
    protected function getPhinxColumnEncoding($phinxType, $attributes, $columnData)
    {
        $allowedTypes = [
            AdapterInterface::PHINX_TYPE_CHAR,
            AdapterInterface::PHINX_TYPE_STRING,
            AdapterInterface::PHINX_TYPE_TEXT,
        ];
        if (!in_array($phinxType, $allowedTypes)) {
            return $attributes;
        }

        if (!empty($columnData['CHARACTER_SET_NAME'])) {
            $attributes[] = '\'encoding\' => "' . addslashes($columnData['CHARACTER_SET_NAME']) . '"';
        }

        return $attributes;
    }

    /**
     * Generate phinx column options (comment).
     *
     * @param array $attributes
     * @param array $columnData
     *
     * @return array Attributes
     */
    protected function getPhinxColumnOptionsComment($attributes, $columnData)
    {
        // Set a text comment on the column
        if (!empty($columnData['COLUMN_COMMENT'])) {
            $attributes[] = '\'comment\' => "' . addslashes($columnData['COLUMN_COMMENT']) . '"';
        }

        return $attributes;
    }

    /**
     * Generate phinx column options (after).
     *
     * @param array $attributes
     * @param array $columnData
     * @param array $columns
     *
     * @return array Attributes
     */
    protected function getPhinxColumnOptionsAfter($attributes, $columnData, $columns)
    {
        $columnName = $columnData['COLUMN_NAME'];
        $after = null;
        foreach (array_keys($columns) as $column) {
            if ($column === $columnName) {
                break;
            }
            $after = $column;
        }
        if (!empty($after)) {
            $attributes[] = sprintf('\'after\' => \'%s\'', $after);
        }

        return $attributes;
    }

    /**
     * Get addColumn method.
     *
     * @param array $schema
     * @param string $table
     * @param string $columnName
     *
     * @return string
     */
    protected function getColumnCreateAddNoUpdate($schema, $table, $columnName)
    {
        $result = $this->getColumnCreate($schema, $table, $columnName);

        return sprintf("%s\$table->addColumn('%s', '%s', %s)->save();", $this->ind2, $result[1], $result[2], $result[3]);
    }

    /**
     * Get addColumn method.
     *
     * @param array $schema
     * @param string $table
     * @param string $columnName
     *
     * @return string
     */
    protected function getColumnCreateAdd($schema, $table, $columnName)
    {
        $result = $this->getColumnCreate($schema, $table, $columnName);

        return sprintf("%s\$table->addColumn('%s', '%s', %s)->update();", $this->ind2, $result[1], $result[2], $result[3]);
    }

    /**
     * Generate column update.
     *
     * @param array $schema
     * @param string $table
     * @param string $columnName
     *
     * @return string
     */
    protected function getColumnUpdate($schema, $table, $columnName)
    {
        $columns = $schema['tables'][$table]['columns'];
        $columnData = $columns[$columnName];

        $phinxType = $this->getPhinxColumnType($columnData);
        $columnAttributes = $this->getPhinxColumnOptions($phinxType, $columnData, $columns);
        $result = sprintf("%s\$this->table(\"%s\")->changeColumn('%s', '%s', $columnAttributes)->update();", $this->ind2, $table, $columnName, $phinxType, $columnAttributes);

        return $result;
    }

    /**
     * Get table migration (old table columns).
     *
     * @param array $output
     * @param string $tableName
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    protected function getTableMigrationOldTablesColumns($output, $tableName, $new, $old)
    {
        if (empty($old['tables'][$tableName]['columns'])) {
            return $output;
        }
        foreach ($old['tables'][$tableName]['columns'] as $oldColumnName => $oldColumnData) {
            if (!isset($new['tables'][$tableName]['columns'][$oldColumnName])) {
                $output[] = $this->getColumnRemove($tableName, $oldColumnName);
            }
        }

        return $output;
    }

    /**
     * Generate column remove.
     *
     * @param string $table
     * @param string $columnName
     *
     * @return string
     */
    protected function getColumnRemove($table, $columnName)
    {
        $output = [];
        $output[] = sprintf("%sif(\$this->table('%s')->hasColumn('%s')) {", $this->ind2, $table, $columnName);
        $output[] = $result = sprintf("%s\$this->table(\"%s\")->removeColumn('%s')->update();", $this->ind3, $table, $columnName);
        $output[] = sprintf('%s}', $this->ind2);
        $result = implode($this->nl, $output);

        return $result;
    }

    /**
     * Get table migration (indexes).
     *
     * @param array $output
     * @param array $table
     * @param string $tableName
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    protected function getTableMigrationIndexes($output, $table, $tableName, $new, $old)
    {
        if (empty($table['indexes'])) {
            return $output;
        }
        foreach ($table['indexes'] as $indexName => $indexSequences) {
            if (!isset($old['tables'][$tableName]['indexes'][$indexName])) {
                $output = $this->getIndexCreate($output, $new, $tableName, $indexName);
            } else {
                if ($this->neq($new, $old, ['tables', $tableName, 'indexes', $indexName])) {
                    $output = $this->getIndexCreate($output, $new, $tableName, $indexName);
                }
            }
        }

        return $output;
    }

    /**
     * Generate index create.
     *
     * @param string[] $output Output
     * @param array $schema Schema
     * @param string $table Tablename
     * @param string $indexName Index name
     *
     * @return array Output
     */
    protected function getIndexCreate($output, $schema, $table, $indexName)
    {
        if ($indexName == 'PRIMARY') {
            return $output;
        }
        $indexes = $schema['tables'][$table]['indexes'];
        $indexSequences = $indexes[$indexName];

        $indexFields = $this->getIndexFields($indexSequences);
        $indexOptions = $this->getIndexOptions(array_values($indexSequences)[0]);

        $output = $this->getIndexRemove($table, $indexName, $output);
        $output[] = $this->getTableWithoutOptionsVariable($table);
        $output[] = sprintf('%s$table->addIndex(%s, %s)->save();', $this->ind2, $indexFields, $indexOptions);

        return $output;
    }

    /**
     * Get index fields.
     *
     * @param array $indexSequences
     *
     * @return string
     */
    public function getIndexFields($indexSequences)
    {
        $indexFields = [];
        foreach ($indexSequences as $indexData) {
            $indexFields[] = $indexData['Column_name'];
        }
        $result = "['" . implode("','", $indexFields) . "']";

        return $result;
    }

    /**
     * Generate index options.
     *
     * @param array $indexData
     *
     * @return string
     */
    public function getIndexOptions($indexData)
    {
        $options = [];

        if (isset($indexData['Key_name'])) {
            $options[] = '\'name\' => "' . $indexData['Key_name'] . '"';
        }
        if (isset($indexData['Non_unique']) && $indexData['Non_unique'] == 1) {
            $options[] = '\'unique\' => false';
        } else {
            $options[] = '\'unique\' => true';
        }
        /*
         * Number of characters for nonbinary string types (CHAR, VARCHAR, TEXT)
         * and number of bytes for binary string types (BINARY, VARBINARY, BLOB)
         */
        if (isset($indexData['Sub_part'])) {
            $options[] = '\'limit\' => ' . $indexData['Sub_part'];
        }
        // MyISAM only
        if (isset($indexData['Index_type']) && $indexData['Index_type'] == 'FULLTEXT') {
            $options[] = '\'type\' => \'fulltext\'';
        }
        $result = '[' . implode(', ', $options) . ']';

        return $result;
    }

    /**
     * Generate index remove.
     *
     * @param string $table
     * @param string $indexName
     * @param array $output
     *
     * @return array
     */
    protected function getIndexRemove($table, $indexName, array $output)
    {
        $output[] = $this->getTableWithoutOptionsVariable($table);
        $output[] = sprintf('%s$table->removeIndexByName("%s");', $this->ind2, $indexName);

        return $output;
    }

    /**
     * Generate foreign keys migrations.
     *
     * @param array $new New schema
     * @param array $old Old schema
     *
     * @return array Output
     */
    protected function getForeignKeysMigrations($new = [], $old = [])
    {
        if (empty($new['tables'])) {
            return [];
        }
        $output = [];
        foreach ($new['tables'] as $tableName => $newTable) {
            if ($tableName === $this->options['default_migration_table']) {
                continue;
            }

            $oldTable = !empty($old['tables'][$tableName]) ? $old['tables'][$tableName] : [];

            if (!empty($oldTable['foreign_keys'])) {
                foreach ($oldTable['foreign_keys'] as $fkName => $fkData) {
                    if (!isset($newTable['foreign_keys'][$fkName])) {
                        $output[] = $this->getForeignKeyRemove($tableName, $fkName);
                    }
                }
            }

            if (!empty($newTable['foreign_keys'])) {
                foreach ($newTable['foreign_keys'] as $fkName => $fkData) {
                    if (!isset($oldTable['foreign_keys'][$fkName])) {
                        $output[] = $this->getForeignKeyCreate($tableName, $fkName);
                    }
                }
            }
        }

        return $output;
    }

    /**
     * Generate foreign key remove.
     *
     * @param string $table
     * @param string $indexName
     *
     * @return string
     */
    protected function getForeignKeyRemove($table, $indexName)
    {
        $result = sprintf("%s\$this->table(\"%s\")->dropForeignKey('%s');", $this->ind2, $table, $indexName);

        return $result;
    }

    /**
     * Generate foreign key create.
     *
     * @param string $table
     * @param string $fkName
     *
     * @return string
     */
    protected function getForeignKeyCreate($table, $fkName)
    {
        $foreignKeys = $this->dba->getForeignKeys($table);
        $fkData = $foreignKeys[$fkName];
        $columns = "'" . $fkData['COLUMN_NAME'] . "'";
        $referencedTable = "'" . $fkData['REFERENCED_TABLE_NAME'] . "'";
        $referencedColumns = "'" . $fkData['REFERENCED_COLUMN_NAME'] . "'";
        $options = $this->getForeignKeyOptions($fkData, $fkName);

        $output = [];
        $output[] = sprintf('%s$this->table("%s")->addForeignKey(%s, %s, %s, %s)->save();', $this->ind2, $table, $columns, $referencedTable, $referencedColumns, $options);

        $result = implode($this->nl, $output);

        return $result;
    }

    /**
     * Generate foreign key options.
     *
     * @param array $fkData
     * @param string $fkName
     *
     * @return string
     */
    protected function getForeignKeyOptions($fkData, $fkName = null)
    {
        $options = [];
        if (isset($fkName)) {
            $options[] = '\'constraint\' => "' . $fkName . '"';
        }
        if (isset($fkData['UPDATE_RULE'])) {
            $options[] = '\'update\' => "' . $this->getForeignKeyRuleValue($fkData['UPDATE_RULE']) . '"';
        }
        if (isset($fkData['DELETE_RULE'])) {
            $options[] = '\'delete\' => "' . $this->getForeignKeyRuleValue($fkData['DELETE_RULE']) . '"';
        }
        $result = '[' . implode(', ', $options) . ']';

        return $result;
    }

    /**
     * Generate foreign key rule value.
     *
     * @param string $value
     *
     * @return string
     */
    protected function getForeignKeyRuleValue($value)
    {
        $value = strtolower($value);
        if ($value == 'no action') {
            return 'NO_ACTION';
        }
        if ($value == 'cascade') {
            return 'CASCADE';
        }
        if ($value == 'restrict') {
            return 'RESTRICT';
        }
        if ($value == 'set null') {
            return 'SET_NULL';
        }

        return 'NO_ACTION';
    }

    /**
     * Append lines.
     *
     * @param array $array1
     * @param array $array2
     *
     * @return array
     */
    protected function appendLines(array $array1 = [], array $array2 = [])
    {
        if (empty($array2)) {
            return $array1;
        }
        foreach ($array2 as $value) {
            $array1[] = $value;
        }

        return $array1;
    }

    /**
     * Get table migration (old tables).
     *
     * @param array $output
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    protected function getTableMigrationOldTables(array $output, array $new, array $old)
    {
        if (empty($old['tables'])) {
            return $output;
        }

        foreach ($old['tables'] as $tableName => $table) {
            if ($tableName == $this->options['default_migration_table']) {
                continue;
            }
            if (!empty($old['tables'][$tableName]['indexes'])) {
                foreach ($old['tables'][$tableName]['indexes'] as $indexName => $indexSequences) {
                    if (!isset($new['tables'][$tableName]['indexes'][$indexName])) {
                        $output = $this->getIndexRemove($tableName, $indexName, $output);
                    }
                }
            }

            if (!isset($new['tables'][$tableName])) {
                $output[] = $this->getDropTable($tableName);
                continue;
            }

            // Detect changes for existing tables (like engine, character set, collation, ...)
            $oldTable = $old['tables'][$tableName]['table'];
            $newTable = $new['tables'][$tableName]['table'];
            if ($oldTable != $newTable) {
                $output = $this->getUpdateTable($output, $new['tables'][$tableName], $tableName);
            }

            // @todo Detect changes on primary keys
        }

        return $output;
    }

    /**
     * Generate drop table.
     *
     * @param string $table
     *
     * @return string
     */
    protected function getDropTable($table)
    {
        return sprintf('%s$this->table("%s")->drop()->save();', $this->ind2, $table);
    }

    /**
     * Generate update table.
     *
     * @param array $output
     * @param array $table
     * @param string $tableName
     *
     * @return array
     */
    protected function getUpdateTable($output, $table, $tableName)
    {
        $output = $this->getCreateTable($output, $table, $tableName);
        $output[] = sprintf('%s$table->save();', $this->ind2);

        return $output;
    }

    /**
     * Generate Alter Table Engine.
     *
     * @param string $table
     * @param string $engine
     *
     * @return string
     */
    protected function getAlterTableEngine($table, $engine)
    {
        $engine = $this->dba->quote($engine);

        return sprintf('%s$this->execute("ALTER TABLE `%s` ENGINE=%s;");', $this->ind2, $table, $engine);
    }

    /**
     * Generate Alter Table Charset.
     *
     * @param string $table
     * @param string $charset
     *
     * @return string
     */
    protected function getAlterTableCharset($table, $charset)
    {
        $table = $this->dba->ident($table);
        $charset = $this->dba->quote($charset);

        return sprintf('%s$this->execute("ALTER TABLE %s CHARSET=%s;");', $this->ind2, $table, $charset);
    }

    /**
     * Generate Alter Table Collate.
     *
     * @param string $table
     * @param string $collate
     *
     * @return string
     */
    protected function getAlterTableCollate($table, $collate)
    {
        $table = $this->dba->ident($table);
        $collate = $this->dba->quote($collate);

        return sprintf('%s$this->execute("ALTER TABLE %s COLLATE=%s;");', $this->ind2, $table, $collate);
    }

    /**
     * Generate alter table comment.
     *
     * @param string $table
     * @param string $comment
     *
     * @return string
     */
    protected function getAlterTableComment($table, $comment)
    {
        $table = $this->dba->ident($table);
        $commentSave = $this->dba->quote($comment);

        return sprintf('%s$this->execute("ALTER TABLE %s COMMENT=%s;");', $this->ind2, $table, $commentSave);
    }
}
