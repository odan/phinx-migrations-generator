<?php

namespace Odan\Migration\Adapter\Generator;

use Odan\Migration\Adapter\Database\SchemaAdapterInterface;
use Odan\Migration\Utility\ArrayUtil;
use Phinx\Db\Adapter\AdapterInterface;
use Riimu\Kit\PHPEncoder\PHPEncoder;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * PhinxMySqlGenerator.
 */
class PhinxMySqlGenerator
{
    /**
     * Database adapter.
     *
     * @var SchemaAdapterInterface
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
     * @param SchemaAdapterInterface $dba
     * @param OutputInterface $output
     * @param mixed $options Options
     */
    public function __construct(SchemaAdapterInterface $dba, OutputInterface $output, $options = [])
    {
        $this->dba = $dba;
        $this->output = $output;

        $default = [
            // Experimental foreign key support.
            'foreign_keys' => false,
            // Default migration table name
            'default_migration_table' => 'phinxlog',
        ];

        $this->options = array_replace_recursive($default, $options) ?: [];
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
    public function createMigration($name, $newSchema, $oldSchema): string
    {
        $className = $this->options['migration_base_class'] ?? '\Phinx\Migration\AbstractMigration';

        $output = [];
        $output[] = '<?php';
        $output[] = '';
        $output[] = 'use Phinx\Db\Adapter\MysqlAdapter;';
        $output[] = '';
        $output[] = sprintf('class %s extends ' . $className, $name);
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
    protected function addChangeMethod($output, $new, $old): array
    {
        $output[] = $this->ind . 'public function change()';
        $output[] = $this->ind . '{';

        if (!empty($this->options['foreign_keys'])) {
            $output[] = $this->getSetUniqueChecks(0);
            $output[] = $this->getSetForeignKeyCheck(0);
        }

        $output = $this->getTableMigrationNewDatabase($output, $new, $old);
        $output = $this->getTableMigrationTables($output, $new, $old);

        if (!empty($this->options['foreign_keys'])) {
            $output[] = $this->getSetForeignKeyCheck(1);
            $output[] = $this->getSetUniqueChecks(1);
        }

        $output[] = $this->ind . '}';

        return $output;
    }

    /**
     * Generate Set Unique Checks.
     *
     * @param int $value 0 or 1
     *
     * @return string
     */
    protected function getSetUniqueChecks($value): string
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
    protected function getSetForeignKeyCheck($value): string
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
    protected function getTableMigrationNewDatabase($output, $new, $old): array
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
    protected function neq($arr, $arr2, $keys): bool
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
    protected function eq($arr, $arr2, $keys): bool
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
    protected function getAlterDatabaseCharset($charset, $database = null): string
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
    protected function getAlterDatabaseCollate($collate, $database = null): string
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
    protected function getTableMigrationTables(array $output, array $new, array $old): array
    {
        $arrayUtil = new ArrayUtil();

        foreach ($new['tables'] ?? [] as $tableName => $table) {
            if ($tableName === $this->options['default_migration_table']) {
                continue;
            }

            $tableDiffs = $arrayUtil->diff($new['tables'][$tableName] ?? [], $old['tables'][$tableName] ?? []);
            $tableDiffsRemove = $arrayUtil->diff($old['tables'][$tableName] ?? [], $new['tables'][$tableName] ?? []);

            if ($tableDiffs || $tableDiffsRemove) {
                $output[] = $this->getTableVariable($table, $tableName);

                // To add or modify
                $output = $this->getTableMigrationNewTablesColumns($output, $table, $tableName, $new, $old);
                $output = $this->getTableMigrationOldTablesColumns($output, $tableName, $new, $old);
                $output = $this->getTableMigrationIndexes($output, $table, $tableName, $new, $old);

                if (!empty($this->options['foreign_keys'])) {
                    $output = $this->getForeignKeysMigrations($output, $tableName, $new, $old);
                }

                if (isset($old['tables'][$tableName])) {
                    // Update existing table
                    $output[] = sprintf('%s->save();', $this->ind3);
                } else {
                    // Create new table
                    $output[] = sprintf('%s->create();', $this->ind3);
                }
            }
        }

        // To remove
        $output = $this->getTableMigrationDropTables($output, $new, $old);

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
    protected function getTableVariable(array $table, string $tableName): string
    {
        $options = $this->getTableOptions($table);
        $result = sprintf('%s$this->table(\'%s\', %s)', $this->ind2, $tableName, $options);

        return $result;
    }

    /**
     * Get table options.
     *
     * @param array $table
     *
     * @return string
     */
    protected function getTableOptions(array $table): string
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

        $result = $this->prettifyArray($attributes, 3);

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
    protected function getPhinxTablePrimaryKey(array $attributes, array $table): array
    {
        $primaryKeys = $this->getPrimaryKeys($table);
        $attributes['id'] = false;

        if (!empty($primaryKeys)) {
            $attributes['primary_key'] = $primaryKeys;
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
    protected function getPrimaryKeys(array $table): array
    {
        $primaryKeys = [];
        foreach ($table['columns'] as $column) {
            $columnName = $column['COLUMN_NAME'];
            $columnKey = $column['COLUMN_KEY'];
            if ($columnKey !== 'PRI') {
                continue;
            }
            $primaryKeys[] = $columnName;
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
    protected function getPhinxTableEngine(array $attributes, array $table): array
    {
        if (!empty($table['table']['engine'])) {
            $attributes['engine'] = $table['table']['engine'];
        } else {
            $attributes['engine'] = 'InnoDB';
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
    protected function getPhinxTableEncoding(array $attributes, array $table): array
    {
        if (!empty($table['table']['character_set_name'])) {
            $attributes['encoding'] = $table['table']['character_set_name'];
        } else {
            $attributes['encoding'] = 'utf8';
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
    protected function getPhinxTableCollation(array $attributes, array $table): array
    {
        if (!empty($table['table']['table_collation'])) {
            $attributes['collation'] = $table['table']['table_collation'];
        } else {
            $attributes['collation'] = 'utf8_general_ci';
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
    protected function getPhinxTableComment(array $attributes, array $table): array
    {
        if (!empty($table['table']['table_comment'])) {
            $attributes['comment'] = $table['table']['table_comment'];
        } else {
            $attributes['comment'] = '';
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
    protected function getPhinxTableRowFormat(array $attributes, array $table): array
    {
        if (!empty($table['table']['row_format'])) {
            $attributes['row_format'] = strtoupper($table['table']['row_format']);
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
    protected function getTableMigrationNewTablesColumns(array $output, array $table, string $tableName, array $new, array $old): array
    {
        if (empty($table['columns'])) {
            return $output;
        }

        $array = new ArrayUtil();

        // Remove not used keys
        $array->unsetArrayKeys($new, 'COLUMN_KEY');
        $array->unsetArrayKeys($old, 'COLUMN_KEY');

        foreach ($table['columns'] as $columnName => $columnData) {
            if (!isset($old['tables'][$tableName]['columns'][$columnName])) {
                $output[] = $this->getColumnCreateAddNoUpdate($new, $tableName, $columnName);
            } else {
                if ($this->neq($new, $old, ['tables', $tableName, 'columns', $columnName])) {
                    $output[] = $this->getColumnUpdate($new, $tableName, $columnName);
                }
            }
        }

        return $output;
    }

    /**
     * Generate column create.
     *
     * @param array $schema
     * @param string $tableName
     * @param string $columnName
     *
     * @return string[]
     */
    protected function getColumnCreate(array $schema, string $tableName, string $columnName): array
    {
        $columns = $schema['tables'][$tableName]['columns'];
        $columnData = $columns[$columnName];
        $phinxType = $this->getPhinxColumnType($columnData);
        $columnAttributes = $this->getPhinxColumnOptions($phinxType, $columnData, $columns);

        return [$tableName, $columnName, $phinxType, $columnAttributes];
    }

    /**
     * Map MySql data type to Phinx\Db\Adapter\AdapterInterface::PHINX_TYPE_*.
     *
     * @param array $columnData
     *
     * @return string
     */
    protected function getPhinxColumnType(array $columnData): string
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
            'bigint' => 'biginteger',
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
    protected function getMySQLColumnType(array $columnData): string
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
    protected function getPhinxColumnOptions(string $phinxType, array $columnData, array $columns): string
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

        $result = $this->prettifyArray($attributes, 3);

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
    protected function getPhinxColumnOptionsNull(array $attributes, array $columnData): array
    {
        // has NULL
        if ($columnData['IS_NULLABLE'] === 'YES') {
            $attributes['null'] = true;
        } else {
            $attributes['null'] = false;
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
    protected function getPhinxColumnOptionsDefault(array $attributes, array $columnData): array
    {
        if ($columnData['COLUMN_DEFAULT'] !== null) {
            $attributes['default'] = $columnData['COLUMN_DEFAULT'];
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
    protected function getPhinxColumnOptionsTimestamp(array $attributes, array $columnData): array
    {
        // default set default value (use with CURRENT_TIMESTAMP)
        // on update CURRENT_TIMESTAMP
        if (strpos($columnData['EXTRA'], 'on update CURRENT_TIMESTAMP') !== false) {
            $attributes['update'] = 'CURRENT_TIMESTAMP';
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
    protected function getPhinxColumnOptionsLimit(array $attributes, array $columnData): array
    {
        $limit = $this->getColumnLimit($columnData);
        if ($limit) {
            $attributes['limit'] = new RawPhpValue($limit);
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
    protected function getColumnLimit(array $columnData): string
    {
        $limit = '0';
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
    protected function getPhinxColumnOptionsNumeric(array $attributes, array $columnData): array
    {
        $dataType = $columnData['DATA_TYPE'];

        $intDefaultLimits = [
            'int' => '11',
            'bigint' => '20',
        ];

        // For integer and biginteger columns
        if ($dataType === 'int' || $dataType === 'bigint') {
            $match = null;
            if (preg_match('/\((\d+)\)/', $columnData['COLUMN_TYPE'], $match) === 1) {
                if ($match[1] !== $intDefaultLimits[$dataType]) {
                    $attributes['limit'] = $match[1];
                }
            }

            // signed enable or disable the unsigned option (only applies to MySQL)
            $match = null;
            $pattern = '/\(\d+\) unsigned$/';
            if (preg_match($pattern, $columnData['COLUMN_TYPE'], $match) === 1) {
                $attributes['signed'] = false;
            }

            // identity enable or disable automatic incrementing
            if ($columnData['EXTRA'] == 'auto_increment') {
                $attributes['identity'] = 'enable';
            }
        }

        // For decimal columns
        if ($dataType === 'decimal') {
            // Set decimal accuracy
            if (!empty($columnData['NUMERIC_PRECISION'])) {
                $attributes['precision'] = $columnData['NUMERIC_PRECISION'];
            }
            if (!empty($columnData['NUMERIC_SCALE'])) {
                $attributes['scale'] = $columnData['NUMERIC_SCALE'];
            }
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
    protected function getOptionEnumValue(array $attributes, array $columnData): array
    {
        $match = null;
        $pattern = '/enum\((.*)\)/';
        if (preg_match($pattern, $columnData['COLUMN_TYPE'], $match) === 1) {
            $values = str_getcsv($match[1], ',', "'", '\\');
            $attributes['values'] = $values;
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
    protected function getPhinxColumnCollation(string $phinxType, array $attributes, array $columnData): array
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
            $attributes['collation'] = $columnData['COLLATION_NAME'];
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
    protected function getPhinxColumnEncoding(string $phinxType, array $attributes, array $columnData): array
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
            $attributes['encoding'] = $columnData['CHARACTER_SET_NAME'];
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
    protected function getPhinxColumnOptionsComment(array $attributes, array $columnData): array
    {
        // Set a text comment on the column
        if (!empty($columnData['COLUMN_COMMENT'])) {
            $attributes['comment'] = $columnData['COLUMN_COMMENT'];
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
    protected function getPhinxColumnOptionsAfter(array $attributes, array $columnData, array $columns): array
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
            $attributes['after'] = $after;
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
    protected function getColumnCreateAddNoUpdate(array $schema, string $table, string $columnName): string
    {
        $result = $this->getColumnCreate($schema, $table, $columnName);

        return sprintf("%s->addColumn('%s', '%s', %s)", $this->ind3, $result[1], $result[2], $result[3]);
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
    protected function getColumnUpdate(array $schema, string $table, string $columnName): string
    {
        $columns = $schema['tables'][$table]['columns'];
        $columnData = $columns[$columnName];

        $phinxType = $this->getPhinxColumnType($columnData);
        $columnAttributes = $this->getPhinxColumnOptions($phinxType, $columnData, $columns);
        $result = sprintf("%s->changeColumn('%s', '%s', $columnAttributes)", $this->ind2, $columnName, $phinxType, $columnAttributes);

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
    protected function getTableMigrationOldTablesColumns(array $output, string $tableName, array $new, array $old): array
    {
        if (empty($old['tables'][$tableName]['columns'])) {
            return $output;
        }

        foreach ($old['tables'][$tableName]['columns'] as $oldColumnName => $oldColumnData) {
            if (!isset($new['tables'][$tableName]['columns'][$oldColumnName])) {
                $output = $this->getColumnRemove($output, $oldColumnName);
            }
        }

        return $output;
    }

    /**
     * Generate column remove.
     *
     * @param array $output
     * @param string $columnName
     *
     * @return array
     */
    protected function getColumnRemove(array $output, string $columnName): array
    {
        $output[] = sprintf("%s->removeColumn('%s')", $this->ind3, $columnName);

        return $output;
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
    protected function getTableMigrationIndexes(array $output, array $table, string $tableName, array $new, array $old): array
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

        // To delete
        if (!empty($old['tables'][$tableName]['indexes'])) {
            foreach ($old['tables'][$tableName]['indexes'] as $indexName => $indexSequences) {
                if (!isset($new['tables'][$tableName]['indexes'][$indexName])) {
                    $output = $this->getIndexRemove($indexName, $output);
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
    protected function getIndexCreate(array $output, array $schema, string $table, string $indexName): array
    {
        if ($indexName == 'PRIMARY') {
            return $output;
        }
        $indexes = $schema['tables'][$table]['indexes'];
        $indexSequences = $indexes[$indexName];

        $indexFields = $this->getIndexFields($indexSequences);
        $indexOptions = $this->getIndexOptions(array_values($indexSequences)[0]);

        $output[] = sprintf('%s->addIndex(%s, %s)', $this->ind2, $indexFields, $indexOptions);

        return $output;
    }

    /**
     * Get index fields.
     *
     * @param array $indexSequences
     *
     * @return string
     */
    protected function getIndexFields(array $indexSequences): string
    {
        $indexFields = [];
        foreach ($indexSequences as $indexData) {
            $indexFields[] = $indexData['Column_name'];
        }

        $result = $this->prettifyArray($indexFields, 3);

        return $result;
    }

    /**
     * Generate index options.
     *
     * @param array $indexData
     *
     * @return string
     */
    protected function getIndexOptions(array $indexData): string
    {
        $options = [];

        if (isset($indexData['Key_name'])) {
            $options['name'] = $indexData['Key_name'];
        }
        if (isset($indexData['Non_unique']) && $indexData['Non_unique'] == 1) {
            $options['unique'] = false;
        } else {
            $options['unique'] = true;
        }

        //Number of characters for nonbinary string types (CHAR, VARCHAR, TEXT)
        // and number of bytes for binary string types (BINARY, VARBINARY, BLOB)
        if (isset($indexData['Sub_part'])) {
            $options['limit'] = $indexData['Sub_part'];
        }
        // MyISAM only
        if (isset($indexData['Index_type']) && $indexData['Index_type'] == 'FULLTEXT') {
            $options['type'] = 'fulltext';
        }

        $result = '';
        if (!empty($options)) {
            $result = $this->prettifyArray($options, 3);
        }

        return $result;
    }

    /**
     * Generate index remove.
     *
     * @param string $indexName
     * @param array $output
     *
     * @return array
     */
    protected function getIndexRemove(string $indexName, array $output): array
    {
        $output[] = sprintf('%s->removeIndexByName("%s")', $this->ind3, $indexName);

        return $output;
    }

    /**
     * Generate foreign keys migrations.
     *
     * @param array $output
     * @param string $tableName
     * @param array $new New schema
     * @param array $old Old schema
     *
     * @return array Output
     */
    protected function getForeignKeysMigrations(array $output, string $tableName, array $new = [], array $old = []): array
    {
        if (empty($new['tables'][$tableName])) {
            return [];
        }

        $newTable = $new['tables'][$tableName];

        $oldTable = !empty($old['tables'][$tableName]) ? $old['tables'][$tableName] : [];

        if (!empty($oldTable['foreign_keys'])) {
            foreach ($oldTable['foreign_keys'] as $fkName => $fkData) {
                if (!isset($newTable['foreign_keys'][$fkName])) {
                    $output = $this->getForeignKeyRemove($output, $fkName);
                }
            }
        }

        if (!empty($newTable['foreign_keys'])) {
            foreach ($newTable['foreign_keys'] as $fkName => $fkData) {
                if (!isset($oldTable['foreign_keys'][$fkName])) {
                    $output = $this->getForeignKeyCreate($output, $fkName, $fkData);
                }
            }
        }

        return $output;
    }

    /**
     * Generate foreign key remove.
     *
     * @param array $output
     * @param string $indexName
     *
     * @return array
     */
    protected function getForeignKeyRemove(array $output, string $indexName): array
    {
        $output[] = sprintf("%s->dropForeignKey('%s')", $this->ind2, $indexName);

        return $output;
    }

    /**
     * Generate foreign key create.
     *
     * @param array $output
     * @param string $fkName
     * @param array $fkData
     *
     * @return array
     */
    protected function getForeignKeyCreate(array $output, string $fkName, array $fkData): array
    {
        $columns = "'" . $fkData['COLUMN_NAME'] . "'";
        $referencedTable = "'" . $fkData['REFERENCED_TABLE_NAME'] . "'";
        $referencedColumns = "'" . $fkData['REFERENCED_COLUMN_NAME'] . "'";
        $options = $this->getForeignKeyOptions($fkData, $fkName);

        $output[] = sprintf('%s->addForeignKey(%s, %s, %s, %s)', $this->ind2, $columns, $referencedTable, $referencedColumns, $options);

        return $output;
    }

    /**
     * Generate foreign key options.
     *
     * @param array $fkData
     * @param string $fkName
     *
     * @return string
     */
    protected function getForeignKeyOptions(array $fkData, string $fkName = null): string
    {
        $options = [];
        if (isset($fkName)) {
            $options['constraint'] = $fkName;
        }
        if (isset($fkData['UPDATE_RULE'])) {
            $options['update'] = $this->getForeignKeyRuleValue($fkData['UPDATE_RULE']);
        }
        if (isset($fkData['DELETE_RULE'])) {
            $options['delete'] = $this->getForeignKeyRuleValue($fkData['DELETE_RULE']);
        }

        $result = $this->prettifyArray($options, 3);

        return $result;
    }

    /**
     * Generate foreign key rule value.
     *
     * @param string $value
     *
     * @return string
     */
    protected function getForeignKeyRuleValue(string $value): string
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
     * Get table migration (old tables).
     *
     * @param array $output
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    protected function getTableMigrationDropTables(array $output, array $new, array $old): array
    {
        if (empty($old['tables'])) {
            return $output;
        }

        foreach ($old['tables'] as $tableName => $table) {
            if ($tableName === $this->options['default_migration_table']) {
                continue;
            }

            if (!isset($new['tables'][$tableName])) {
                $output[] = $this->getDropTable($tableName);
                continue;
            }
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
    protected function getDropTable(string $table): string
    {
        return sprintf('%s$this->table(\'%s\')->drop()->save();', $this->ind2, $table);
    }

    /**
     * Prettify array.
     *
     * @param array $variable Array to prettify
     * @param int $tabCount Initial tab count
     *
     * @return string
     */
    protected function prettifyArray(array $variable, int $tabCount): string
    {
        $encoder = new PHPEncoder();

        return $encoder->encode($variable, [
            'array.base' => $tabCount * 4,
            'array.inline' => true,
            'array.indent' => 4,
            'array.eol' => "\n",
        ]);
    }
}
