<?php

namespace Odan\Migration\Adapter\Generator;

use Odan\Migration\Adapter\Database\MySqlAdapter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * PhinxMySqlGenerator
 */
class PhinxMySqlGenerator
{

    /**
     * Database adapter
     *
     * @var MySqlAdapter
     */
    protected $dba;

    /**
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * Options
     *
     * @var array
     */
    protected $options = array();

    /**
     * PSR-2: All PHP files MUST use the Unix LF (linefeed) line ending.
     *
     * @var string
     */
    protected $nl = "\n";

    /**
     *
     * @var string
     */
    protected $ind = '    ';

    /**
     *
     * @var string
     */
    protected $ind2 = '        ';

    /**
     *
     * @var string
     */
    protected $ind3 = '            ';

    /**
     * Concstructor
     *
     * @param MySqlAdapter $dba
     * @param OutputInterface $output
     */
    public function __construct(MySqlAdapter $dba, OutputInterface $output, $options = array())
    {
        $this->dba = $dba;
        $this->output = $output;

        // Experimental foreign key support.
        // Currently phinx can't define a contraint name.
        // https://github.com/robmorgan/phinx/issues/823#issuecomment-231548829
        $default = [
            'foreign_keys' => false
        ];
        $this->options = array_replace_recursive($default, $options);
    }

    /**
     * Create migration
     *
     * @param string $name Name of the migration
     * @param array $newSchema
     * @param array $oldSchema
     * @return string PHP code
     */
    public function createMigration($name, $newSchema, $oldSchema)
    {
        $output = array();
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
     * @param array $output Output
     * @param array $new New schema
     * @param array $old Old schema
     * @return array Output
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
     * @param array $output Output
     * @param array $new New schema
     * @param array $old Old schema
     * @return array Output
     */
    public function getTableMigration($output, $new, $old)
    {
        if (!empty($this->options['foreign_keys'])) {
            $output[] = $this->getSetUniqueChecks(0);
            $output[] = $this->getSetForeignKeyCheck(0);
        }

        if (!empty($new['database'])) {
            if ($this->neq($new, $old, ['database', 'default_character_set_name'])) {
                $output[] = $this->getAlterDatabaseCharset($new['database']['default_character_set_name']);
            }
            if ($this->neq($new, $old, ['database', 'default_collation_name'])) {
                $output[] = $this->getAlterDatabaseCollate($new['database']['default_collation_name']);
            }
        }

        if (!empty($new['tables'])) {
            foreach ($new['tables'] as $tableName => $table) {
                if ($tableName == 'phinxlog') {
                    continue;
                }
                if (!isset($old['tables'][$tableName])) {
                    // create the table
                    $output[] = $this->getCreateTable($tableName);
                }
                if ($this->neq($new, $old, ['tables', $tableName, 'table', 'engine'])) {
                    $output[] = $this->getAlterTableEngine($tableName, $table['table']['engine']);
                }
                if ($this->neq($new, $old, ['tables', $tableName, 'table', 'table_comment'])) {
                    $output[] = $this->getAlterTableComment($tableName, $table['table']['table_comment']);
                }
                if ($this->neq($new, $old, ['tables', $tableName, 'table', 'character_set_name'])) {
                    $output[] = $this->getAlterTableCharset($tableName, $table['table']['character_set_name']);
                }
                if ($this->neq($new, $old, ['tables', $tableName, 'table', 'table_collation'])) {
                    $output[] = $this->getAlterTableCollate($tableName, $table['table']['table_collation']);
                }

                if (!empty($table['columns'])) {
                    foreach ($table['columns'] as $columnName => $columnData) {
                        if (!isset($old['tables'][$tableName]['columns'][$columnName])) {
                            $output[] = $this->getColumnCreate($new, $tableName, $columnName, $columnData);
                        } else {
                            if ($this->neq($new, $old, ['tables', $tableName, 'columns', $columnName])) {
                                $output[] = $this->getColumnUpdate($new, $tableName, $columnName, $columnData);
                            }
                        }
                    }
                }

                if (!empty($old['tables'][$tableName]['columns'])) {
                    foreach ($old['tables'][$tableName]['columns'] as $oldColumnName => $oldColumnData) {
                        if (!isset($new['tables'][$tableName]['columns'][$oldColumnName])) {
                            $output[] = $this->getColumnRemove($tableName, $oldColumnName);
                        }
                    }
                }

                if (!empty($table['indexes'])) {
                    foreach ($table['indexes'] as $indexName => $indexSequences) {
                        if (!isset($old['tables'][$tableName]['indexes'][$indexName])) {
                            $output[] = $this->getIndexCreate($new, $tableName, $indexName);
                        } else {
                            if ($this->neq($new, $old, ['tables', $tableName, 'indexes', $indexName])) {
                                $output[] = $this->getIndexCreate($new, $tableName, $indexName);
                            }
                        }
                    }
                }
            }
        }

        if (!empty($this->options['foreign_keys'])) {
            $lines = $this->getForeignKeysMigrations($new, $old);
            $output = $this->appendLines($output, $lines);
        }

        if (!empty($old['tables'])) {
            foreach ($old['tables'] as $tableName => $table) {
                if ($tableName == 'phinxlog') {
                    continue;
                }

                if (!empty($old['tables'][$tableName]['indexes'])) {
                    foreach ($old['tables'][$tableName]['indexes'] as $indexName => $indexSequences) {
                        if (!isset($new['tables'][$tableName]['indexes'][$indexName])) {
                            $output[] = $this->getIndexRemove($tableName, $indexName);
                        }
                    }
                }
                if (!isset($new['tables'][$tableName])) {
                    $output[] = $this->getDropTable($tableName);
                }
            }
        }

        if (!empty($this->options['foreign_keys'])) {
            $output[] = $this->getSetForeignKeyCheck(1);
            $output[] = $this->getSetUniqueChecks(1);
        }

        return $output;
    }

    /**
     * Append lines
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    protected function appendLines($array1, $array2)
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
     * Generate foreign keys migrations.
     *
     * @param array $new New schema
     * @param array $old Old schema
     * @return array Output
     */
    protected function getForeignKeysMigrations($new, $old)
    {
        if (empty($new['tables'])) {
            return null;
        }
        $output = [];
        foreach ($new['tables'] as $tableName => $table) {
            if ($tableName == 'phinxlog') {
                continue;
            }
            if (empty($table['foreign_keys'])) {
                continue;
            }
            foreach ($table['foreign_keys'] as $fkName => $fkData) {
                if (!isset($old['tables'][$tableName]['foreign_keys'][$fkName])) {
                    $output[] = $this->getForeignKeyCreate($tableName, $fkName, $fkData);
                } else {
                    $output[] = $this->getForeignKeyRemove($tableName, $fkName, $fkData);
                }
            }
        }
        return $output;
    }

    /**
     * Generate alter database charset.
     *
     * @param string $charset
     * @param string $database
     * @return string
     */
    protected function getAlterDatabaseCharset($charset, $database = null)
    {
        if ($database) {
            $database = ' ' . $this->dba->ident($database);
        }
        $charset = $this->dba->quote($charset);
        return sprintf("%s\$this->execute(\"ALTER DATABASE%s CHARACTER SET %s;\");", $this->ind2, $database, $charset);
    }

    /**
     * Generate alter database collate.
     *
     * @param string $collate
     * @param string $database
     * @return string
     */
    protected function getAlterDatabaseCollate($collate, $database = null)
    {
        if ($database) {
            $database = ' ' . $this->dba->ident($database);
        }
        $collate = $this->dba->quote($collate);
        return sprintf("%s\$this->execute(\"ALTER DATABASE%s COLLATE=%s;\");", $this->ind2, $database, $collate);
    }

    /**
     * Generate create table.
     *
     * @param string $table
     * @return string
     */
    protected function getCreateTable($table)
    {
        return sprintf("%s\$this->table(\"%s\")->save();", $this->ind2, $table);
    }

    /**
     * Generate drop table.
     *
     * @param string $table
     * @return string
     */
    protected function getDropTable($table)
    {
        return sprintf("%s\$this->dropTable(\"%s\");", $this->ind2, $table);
    }

    /**
     * Generate Alter Table Engine.
     * @param string $table
     * @param string $engine
     * @return string
     */
    protected function getAlterTableEngine($table, $engine)
    {
        $engine = $this->dba->quote($engine);
        return sprintf("%s\$this->execute(\"ALTER TABLE `%s` ENGINE=%s;\");", $this->ind2, $table, $engine);
    }

    /**
     * Generate Alter Table Charset.
     *
     * @param string $table
     * @param string $charset
     * @return string
     */
    protected function getAlterTableCharset($table, $charset)
    {
        $table = $this->dba->ident($table);
        $charset = $this->dba->quote($charset);
        return sprintf("%s\$this->execute(\"ALTER TABLE %s CHARSET=%s;\");", $this->ind2, $table, $charset);
    }

    /**
     * Generate Alter Table Collate
     *
     * @param string $table
     * @param string $collate
     * @return string
     */
    protected function getAlterTableCollate($table, $collate)
    {
        $table = $this->dba->ident($table);
        $collate = $this->dba->quote($collate);
        return sprintf("%s\$this->execute(\"ALTER TABLE %s COLLATE=%s;\");", $this->ind2, $table, $collate);
    }

    /**
     * Generate alter table comment.
     *
     * @param string $table
     * @param string $comment
     * @return string
     */
    protected function getAlterTableComment($table, $comment)
    {
        $table = $this->dba->ident($table);
        $commentSave = $this->dba->quote($comment);
        return sprintf("%s\$this->execute(\"ALTER TABLE %s COMMENT=%s;\");", $this->ind2, $table, $commentSave);
    }

    /**
     * Generate column create.
     *
     * @param array $schema
     * @param string $table
     * @param string $columnName
     * @return string
     */
    protected function getColumnCreate($schema, $table, $columnName)
    {
        $columns = $schema['tables'][$table]['columns'];
        $columnData = $columns[$columnName];
        $phinxType = $this->getPhinxColumnType($columnData);
        $columnAttributes = $this->getPhinxColumnOptions($phinxType, $columnData, $columns);

        $output = [];
        if ($columnName == 'id') {
            $output[] = sprintf("%sif (\$this->table('%s')->hasColumn('%s')) {", $this->ind2, $table, $columnName);
            $output[] = $result = sprintf("%s\$this->table(\"%s\")->changeColumn('%s', '%s', %s)->update();", $this->ind3, $table, $columnName, $phinxType, $columnAttributes);
            $output[] = sprintf("%s} else {", $this->ind2);
            $output[] = sprintf("%s\$this->table(\"%s\")->addColumn('%s', '%s', %s)->update();", $this->ind3, $table, $columnName, $phinxType, $columnAttributes);
            $output[] = sprintf("%s}", $this->ind2);
        } else {
            $output[] = sprintf("%s\$this->table(\"%s\")->addColumn('%s', '%s', %s)->update();", $this->ind2, $table, $columnName, $phinxType, $columnAttributes);
        }

        $result = implode($this->nl, $output);
        return $result;
    }

    /**
     * Generate column update.
     *
     * @param array $schema
     * @param string $table
     * @param string $columnName
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
     * Generate column remove.
     *
     * @param string $table
     * @param string $columnName
     * @return string
     */
    protected function getColumnRemove($table, $columnName)
    {
        $output = [];
        $output[] = sprintf("%sif(\$this->table('%s')->hasColumn('%s')) {", $this->ind2, $table, $columnName);
        $output[] = $result = sprintf("%s\$this->table(\"%s\")->removeColumn('%s')->update();", $this->ind3, $table, $columnName);
        $output[] = sprintf("%s}", $this->ind2);
        $result = implode($this->nl, $output);
        return $result;
    }

    /**
     * Get column type.
     *
     * @param array $columnData
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
     * Map MySql data type to Phinx\Db\Adapter\AdapterInterface::PHINX_TYPE_*
     *
     * @param array $columnData
     * @return string
     */
    function getPhinxColumnType($columnData)
    {
        $columnType = $columnData['COLUMN_TYPE'];
        if ($columnType == 'tinyint(1)') {
            return 'boolean';
        }

        // [double] is not supported by phinx
        // https://github.com/robmorgan/phinx/issues/498
        //
        // [bit ] and [year] is also not supported by phinx.

        $type = $this->getMySQLColumnType($columnData);
        switch ($type) {
            case 'tinyint':
            case 'smallint':
            case 'int':
            case 'mediumint':
            case 'bigint':
                return 'integer';
            case 'timestamp':
                return 'timestamp';
            case 'date':
                return 'date';
            case 'datetime':
                return 'datetime';
            case 'time':
                return 'time';
            case 'enum':
                return 'enum';
            case 'set':
                return 'set';
            case 'char':
                return 'char';
            case 'text':
            case 'tinytext':
            case 'mediumtext':
            case 'longtext':
                return 'text';
            case 'varchar':
                return 'string';
            case 'decimal':
                return 'decimal';
            case 'binary':
                return 'binary';
            case 'blob':
            case 'tinyblob':
            case 'mediumblob':
            case 'longblob':
                return 'blob';
            case 'blob':
            case 'longblob':
                return 'blob';
            case 'float':
                return 'float';
            case 'varbinary':
                return 'varbinary';
            default:
                return '[' . $type . ']';
        }
    }

    /**
     * Generate phinx column options.
     *
     * https://media.readthedocs.org/pdf/phinx/latest/phinx.pdf
     *
     * @param string $phinxtype
     * @param array $columnData
     * @param array $columns
     * @return string
     */
    protected function getPhinxColumnOptions($phinxtype, $columnData, $columns)
    {
        $attributes = array();

        // has NULL
        if ($columnData['IS_NULLABLE'] === 'YES') {
            $attributes[] = '\'null\' => true';
        } else {
            $attributes[] = '\'null\' => false';
        }

        // default value
        if ($columnData['COLUMN_DEFAULT'] !== null) {
            $default = is_int($columnData['COLUMN_DEFAULT']) ? $columnData['COLUMN_DEFAULT'] : '\'' . $columnData['COLUMN_DEFAULT'] . '\'';
            $attributes[] = '\'default\' => ' . $default;
        }

        // For timestamp columns:
        // default set default value (use with CURRENT_TIMESTAMP)
        // on update CURRENT_TIMESTAMP
        if (strpos($columnData['EXTRA'], 'on update CURRENT_TIMESTAMP') !== false) {
            $attributes[] = '\'update\' => \'CURRENT_TIMESTAMP\'';
        }
        // limit / length
        $limit = $this->getColumnLimit($columnData);
        if ($limit) {
            $attributes[] = '\'limit\' => ' . $limit;
        }

        // For decimal columns
        if (!empty($columnData['NUMERIC_PRECISION'])) {
            $attributes[] = '\'precision\' => ' . $columnData['NUMERIC_PRECISION'];
        }
        if (!empty($columnData['NUMERIC_SCALE'])) {
            $attributes[] = '\'scale\' => ' . $columnData['NUMERIC_SCALE'];
        }

        // signed enable or disable the unsigned option (only applies to MySQL)
        $pattern = '/\(\d+\) unsigned$/';
        if (preg_match($pattern, $columnData['COLUMN_TYPE'], $match) === 1) {
            $attributes[] = '\'signed\' => false';
        }
        // enum values
        if ($phinxtype === 'enum') {
            //$attributes[] = '\'values\' => ' . str_replace('enum', 'array', $columnData['column_type']);
            $attributes[] = $this->getOptionEnumValue($columnData);
        }

        // Set a text comment on the column
        if (!empty($columnData['COLUMN_COMMENT'])) {
            $attributes[] = '\'comment\' => "' . addslashes($columnData['COLUMN_COMMENT']) . '"';
        }

        // For integer and biginteger columns:
        // identity enable or disable automatic incrementing
        if ($columnData['EXTRA'] == 'auto_increment') {
            $attributes[] = '\'identity\' => \'enable\'';
        }

        // after: specify the column that a new column should be placed after
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

        // @todo
        // update set an action to be triggered when the row is updated (use with CURRENT_TIMESTAMP)
        // timezone enable or disable the with time zone option for time and timestamp columns (only applies to Postgres)
        //
        // For foreign key definitions:
        // update set an action to be triggered when the row is updated
        // delete set an action to be triggered when the row is deleted

        return 'array(' . implode(', ', $attributes) . ')';
    }

    /**
     * Generate option enum values.
     *
     * @param array $columnData
     * @return string
     */
    public function getOptionEnumValue($columnData)
    {
        $match = null;
        $pattern = '/enum\((.*)\)/';
        if (preg_match($pattern, $columnData['COLUMN_TYPE'], $match) === 1) {
            $values = str_getcsv($match[1], ',', "'", "\\");
            foreach ($values as $k => $value) {
                $values[$k] = "'" . addcslashes($value, "'") . "'";
            }
            $valueList = implode(',', array_values($values));
            $arr = sprintf('array(%s)', $valueList);
            $result = sprintf('\'values\' => %s', $arr);
            return $result;
        }
    }

    /**
     * Generate column limit.
     *
     * @param array $columnData
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
     * Generate index create.
     *
     * @param array $schema
     * @param string $table
     * @param string $indexName
     * @return string
     */
    protected function getIndexCreate($schema, $table, $indexName)
    {
        if ($indexName == 'PRIMARY') {
            return '';
        }
        $indexes = $schema['tables'][$table]['indexes'];
        $indexSequences = $indexes[$indexName];

        $indexFields = $this->getIndexFields($indexSequences);
        $indexOptions = $this->getIndexOptions(array_values($indexSequences)[0]);

        $output = [];
        $output[] = sprintf("%sif(\$this->table('%s')->hasIndex('%s')) {", $this->ind2, $table, $indexName);
        $output[] = sprintf("%s%s", $this->ind, $this->getIndexRemove($table, $indexName));
        $output[] = sprintf("%s}", $this->ind2);
        $output[] = sprintf("%s\$this->table(\"%s\")->addIndex(%s, %s)->save();", $this->ind2, $table, $indexFields, $indexOptions);

        $result = implode($this->nl, $output);
        return $result;
    }

    /**
     * Get index fields.
     *
     * @param array $indexSequences
     * @return string
     */
    public function getIndexFields($indexSequences)
    {
        $indexFields = array();
        foreach ($indexSequences as $indexData) {
            $indexFields[] = $indexData['Column_name'];
        }
        $result = "array('" . implode("','", $indexFields) . "')";
        return $result;
    }

    /**
     * Generate index options.
     *
     * @param array $indexData
     * @return string
     */
    function getIndexOptions($indexData)
    {
        $options = array();

        if (isset($indexData['KEY_NAME'])) {
            $options[] = '\'name\' => "' . $indexData['KEY_NAME'] . '"';
        }
        if (isset($indexData['NON_UNIQUE']) && $indexData['NON_UNIQUE'] == 1) {
            $options[] = '\'unique\' => false';
        } else {
            $options[] = '\'unique\' => true';
        }
        // MyISAM only
        if (isset($indexData['INDEX_TYPE']) && $indexData['INDEX_TYPE'] == 'FULLTEXT') {
            $options[] = '\'type\' => \'fulltext\'';
        }
        $result = 'array(' . implode(', ', $options) . ')';
        return $result;
    }

    /**
     * Generate index remove.
     *
     * @param string $table
     * @param string $indexName
     * @return string
     */
    protected function getIndexRemove($table, $indexName)
    {
        $result = sprintf("%s\$this->table(\"%s\")->removeIndexByName('%s');", $this->ind2, $table, $indexName);
        return $result;
    }

    /**
     * Generate foreign key create.
     *
     * @param string $table
     * @param string $fkName
     * @return string
     */
    protected function getForeignKeyCreate($table, $fkName)
    {
        $foreignKeys = $this->dba->getForeignKeys($table);
        $fkData = $foreignKeys[$fkName];
        $columns = "'" . $fkData['COLUMN_NAME'] . "'";
        $referencedTable = "'" . $fkData['REFERENCED_TABLE_NAME'] . "'";
        $referencedColumns = "'" . $fkData['REFERENCED_COLUMN_NAME'] . "'";
        $options = $this->getForeignKeyOptions($fkData);

        $output = [];
        $output[] = sprintf("%s\$this->table(\"%s\")->addForeignKey(%s, %s, %s, %s)->save();", $this->ind2, $table, $columns, $referencedTable, $referencedColumns, $options);

        $result = implode($this->nl, $output);
        return $result;
    }

    /**
     * Generate foreign key options.
     *
     * @param array $fkData
     * @return string
     */
    protected function getForeignKeyOptions($fkData)
    {
        $options = array();
        if (isset($fkData['UPDATE_RULE'])) {
            $options[] = '\'update\' => "' . $this->getForeignKeyRuleValue($fkData['UPDATE_RULE']) . '"';
        }
        if (isset($fkData['delete_rule'])) {
            $options[] = '\'delete\' => "' . $this->getForeignKeyRuleValue($fkData['DELETE_RULE']) . '"';
        }
        // @todo 'constraint'
        $result = 'array(' . implode(', ', $options) . ')';
        return $result;
    }

    /**
     * Generate foreign key rule value.
     *
     * @param string $value
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
     * Generate foreign key remove.
     *
     * @param string $table
     * @param string $indexName
     * @return string
     */
    protected function getForeignKeyRemove($table, $indexName)
    {
        $result = sprintf("%s\$this->table(\"%s\")->dropForeignKey('%s');", $this->ind2, $table, $indexName);
        return $result;
    }

    /**
     * Generate SetForeignKeyCheck.
     *
     * @param int $value
     * @return string
     */
    protected function getSetForeignKeyCheck($value)
    {
        return sprintf("%s\$this->execute(\"SET FOREIGN_KEY_CHECKS = %s;\");", $this->ind2, $value);
    }

    /**
     * Generate Set Unique Checks.
     *
     * @param int $value 0 or 1
     * @return string
     */
    protected function getSetUniqueChecks($value)
    {
        return sprintf("%s\$this->execute(\"SET UNIQUE_CHECKS = %s;\");", $this->ind2, $value);
    }

    /**
     * Compare array
     *
     * @param array $arr
     * @param array $arr2
     * @param array $keys
     * @return bool
     */
    protected function eq($arr, $arr2, $keys)
    {
        $val1 = $this->find($arr, $keys);
        $val2 = $this->find($arr2, $keys);
        return $val1 === $val2;
    }

    /**
     * Compare array (not)
     *
     * @param array $arr
     * @param array $arr2
     * @param array $keys
     * @return bool
     */
    protected function neq($arr, $arr2, $key)
    {
        return !$this->eq($arr, $arr2, $key);
    }

    /**
     * Get array value by keys.
     *
     * @param array $array
     * @param array $parts
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

}
