<?php

namespace Odan\Migration\Adapter\Generator;

use Exception;
use Odan\Migration\Adapter\Database\DatabaseAdapterInterface;
//use Odan\Migration\Adapter\Generator\GeneratorInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phinx\Migration\AbstractMigration;

/**
 * PhinxGenerator
 */
class PhinxGenerator implements GeneratorInterface
{

    /**
     * Database adapter
     *
     * @var \Odan\Migration\Adapter\Database\MySqlAdapter
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
     *
     * @param \Odan\Migration\Adapter\Database\MySqlAdapter $dba
     * @param \Odan\Migration\Adapter\Generator\OutputInterface $output
     */
    public function __construct(\Odan\Migration\Adapter\Database\MySqlAdapter $dba, OutputInterface $output, $options = array())
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
     * @param string $name Name
     * @param array $diffs
     * @return string PHP code
     */
    public function createMigration($name, $diffs)
    {
        $output = array();
        $output[] = '<?php';
        $output[] = '';
        $output[] = 'use Phinx\Migration\AbstractMigration;';
        $output[] = 'use Phinx\Db\Adapter\MysqlAdapter;';
        $output[] = '';
        $output[] = sprintf('class %s extends AbstractMigration', $name);
        $output[] = '{';
        $output = $this->addChangeMethod($output, $diffs[0], $diffs[1]);
        $output[] = '}';
        $output[] = '';
        $result = implode($this->nl, $output);
        return $result;
    }

    public function addChangeMethod($output, $new, $old)
    {
        $output[] = $this->ind . 'public function change()';
        $output[] = $this->ind . '{';
        $output = $this->getTableMigration($output, $new, $old);
        $output[] = $this->ind . '}';
        return $output;
    }

    public function getTableMigration($output, $new, $old)
    {
        if (!empty($this->options['foreign_keys'])) {
            $output[] = $this->getSetUniqueChecks(0);
            $output[] = $this->getSetForeignKeyCheck(0);
        }

        if (!empty($new['database'])) {
            if (isset($new['database']['default_character_set_name'])) {
                $output[] = $this->getAlterDatabaseCharset($new['database']['default_character_set_name']);
            }
            if (isset($new['database']['default_collation_name'])) {
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
                if (isset($table['table']['engine'])) {
                    $output[] = $this->getAlterTableEngine($tableName, $table['table']['engine']);
                }
                if (isset($table['table']['table_comment'])) {
                    $output[] = $this->getAlterTableComment($tableName, $table['table']['table_comment']);
                }
                if (isset($table['table']['character_set_name'])) {
                    $output[] = $this->getAlterTableCharset($tableName, $table['table']['character_set_name']);
                }
                if (isset($table['table']['table_collation'])) {
                    $output[] = $this->getAlterTableCollate($tableName, $table['table']['table_collation']);
                }

                if (!empty($table['columns'])) {
                    foreach ($table['columns'] as $columnName => $columnData) {
                        if (!isset($old['tables'][$tableName]['columns'][$columnName])) {
                            $output[] = $this->getColumnCreate($tableName, $columnName, $columnData);
                        } else {
                            $output[] = $this->getColumnUpdate($tableName, $columnName, $columnData);
                        }
                    }
                }
                if (!empty($table['indexes'])) {
                    foreach ($table['indexes'] as $indexName => $indexData) {
                        if (!isset($old['tables'][$tableName]['indexes'][$indexName])) {
                            $output[] = $this->getIndexCreate($tableName, $indexName, $indexData);
                        } else {
                            $output[] = $this->getIndexRemove($tableName, $indexName, $indexData);
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
     *
     * @param type $new
     * @param type $old
     * @return type
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

    protected function getAlterDatabaseCharset($charset, $database = null)
    {
        if ($database) {
            $database = ' ' . $this->dba->ident($database);
        }
        $charset = $this->dba->quote($charset);
        return sprintf("%s\$this->execute(\"ALTER DATABASE%s CHARACTER SET %s;\");", $this->ind2, $database, $charset);
    }

    protected function getAlterDatabaseCollate($collate, $database = null)
    {
        if ($database) {
            $database = ' ' . $this->dba->ident($database);
        }
        $collate = $this->dba->quote($collate);
        return sprintf("%s\$this->execute(\"ALTER DATABASE%s COLLATE=%s;\");", $this->ind2, $database, $collate);
    }

    protected function getCreateTable($table)
    {
        return sprintf("%s\$this->table(\"%s\")->save();", $this->ind2, $table);
        //return sprintf("%s\$this->table(\"%s\", array('id' => false, 'primary_key' => false))->save();", $this->ind2, $table);
    }

    protected function getDropTable($table)
    {
        return sprintf("%s\$this->dropTable(\"%s\");", $this->ind2, $table);
    }

    protected function getAlterTableEngine($table, $engine)
    {
        $engine = $this->dba->quote($engine);
        return sprintf("%s\$this->execute(\"ALTER TABLE `%s` ENGINE=%s;\");", $this->ind2, $table, $engine);
    }

    protected function getAlterTableCharset($table, $charset)
    {
        $table = $this->dba->ident($table);
        $charset = $this->dba->quote($charset);
        return sprintf("%s\$this->execute(\"ALTER TABLE %s CHARSET=%s;\");", $this->ind2, $table, $charset);
    }

    protected function getAlterTableCollate($table, $collate)
    {
        $table = $this->dba->ident($table);
        $collate = $this->dba->quote($collate);
        return sprintf("%s\$this->execute(\"ALTER TABLE %s COLLATE=%s;\");", $this->ind2, $table, $collate);
    }

    protected function getAlterTableComment($table, $comment)
    {
        $table = $this->dba->ident($table);
        $commentSave = $this->dba->quote($comment);
        return sprintf("%s\$this->execute(\"ALTER TABLE %s COMMENT=%s;\");", $this->ind2, $table, $commentSave);
    }

    protected function getColumnCreate($table, $columnName, $columnData)
    {
        $columns = $this->dba->getColumns($table);
        $columnData = $columns[$columnName];
        $phinxType = $this->getPhinxColumnType($columnData);
        $columnAttributes = $this->getPhinxColumnOptions($phinxType, $columnData, $columns);

        $output = [];
        if ($columnName == 'id') {
            $output[] = sprintf("%sif(\$this->table('%s')->hasColumn('%s')) {", $this->ind2, $table, $columnName, $phinxType, $columnAttributes);
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

    protected function getColumnUpdate($table, $columnName, $columnData)
    {
        $columns = $this->dba->getColumns($table);
        $columnData = $columns[$columnName];

        $phinxType = $this->getPhinxColumnType($columnData);
        $columnAttributes = $this->getPhinxColumnOptions($phinxType, $columnData, $columns);
        $result = sprintf("%s\$this->table(\"%s\")->changeColumn('%s', '%s', $columnAttributes)->update();", $this->ind2, $table, $columnName, $phinxType, $columnAttributes);
        return $result;
    }

    protected function getMySQLColumnType($columnData)
    {
        $type = $columnData['column_type'];
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
        $columnType = $columnData['column_type'];
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
     *
     * https://media.readthedocs.org/pdf/phinx/latest/phinx.pdf
     *
     * @param type $phinxtype
     * @param type $columnData
     * @param array $columns Description
     * @return type
     */
    protected function getPhinxColumnOptions($phinxtype, $columnData, $columns)
    {
        $attributes = array();

        // has NULL
        if ($columnData['is_nullable'] === 'YES') {
            $attributes[] = '\'null\' => true';
        } else {
            $attributes[] = '\'null\' => false';
        }

        // default value
        if ($columnData['column_default'] !== null) {
            $default = is_int($columnData['column_default']) ? $columnData['column_default'] : '\'' . $columnData['column_default'] . '\'';
            $attributes[] = '\'default\' => ' . $default;
        }

        // For timestamp columns:
        // default set default value (use with CURRENT_TIMESTAMP)
        // on update CURRENT_TIMESTAMP
        if (strpos($columnData['extra'], 'on update CURRENT_TIMESTAMP') !== false) {
            $attributes[] = '\'update\' => \'CURRENT_TIMESTAMP\'';
        }
        // limit / length
        $limit = $this->getColumnLimit($columnData);
        if ($limit) {
            $attributes[] = '\'limit\' => ' . $limit;
        }

        // For decimal columns
        if (!empty($columnData['numeric_precision'])) {
            $attributes[] = '\'precision\' => ' . $columnData['numeric_precision'];
        }
        if (!empty($columnData['numeric_scale'])) {
            $attributes[] = '\'scale\' => ' . $columnData['numeric_scale'];
        }

        // signed enable or disable the unsigned option (only applies to MySQL)
        $pattern = '/\(\d+\) unsigned$/';
        if (preg_match($pattern, $columnData['column_type'], $match) === 1) {
            $attributes[] = '\'signed\' => false';
        }
        // enum values
        if ($phinxtype === 'enum') {
            //$attributes[] = '\'values\' => ' . str_replace('enum', 'array', $columnData['column_type']);
            $attributes[] = $this->getOptionEnumValue($columnData);
        }

        // Set a text comment on the column
        if (!empty($columnData['column_comment'])) {
            $attributes[] = '\'comment\' => "' . addslashes($columnData['column_comment']) . '"';
        }

        // For integer and biginteger columns:
        // identity enable or disable automatic incrementing
        if ($columnData['extra'] == 'auto_increment') {
            $attributes[] = '\'identity\' => \'enable\'';
        }

        // after: specify the column that a new column should be placed after
        $columnName = $columnData['column_name'];
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

    public function getOptionEnumValue($columnData)
    {
        $match = null;
        $pattern = '/enum\((.*)\)/';
        if (preg_match($pattern, $columnData['column_type'], $match) === 1) {
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
                if (!empty($columnData['character_maximum_length'])) {
                    $limit = $columnData['character_maximum_length'];
                } else {
                    $pattern = '/\((\d+)\)/';
                    if (preg_match($pattern, $columnData['column_type'], $match) === 1) {
                        $limit = $match[1];
                    }
                }
        }
        return $limit;
    }

    protected function getIndexCreate($table, $indexName, $indexData)
    {
        if ($indexName == 'PRIMARY') {
            return '';
        }
        if (isset($indexData['column_name'])) {
            $indexName = $indexData['column_name'];
        }
        $indexOptions = $this->getIndexOptions($indexData);

        $output = [];
        $output[] = sprintf("%sif(\$this->table('%s')->hasIndex('%s')) {", $this->ind2, $table, $indexName);
        $output[] = sprintf("%s%s", $this->ind, $this->getIndexRemove($table, $indexName));
        $output[] = sprintf("%s}", $this->ind2);
        $output[] = sprintf("%s\$this->table(\"%s\")->addIndex('%s', %s)->save();", $this->ind2, $table, $indexName, $indexOptions);

        $result = implode($this->nl, $output);
        return $result;
    }

    function getIndexOptions($indexData)
    {
        $options = array();

        if (isset($indexData['key_name'])) {
            $options[] = '\'name\' => "' . $indexData['key_name'] . '"';
        }
        if (isset($indexData['non_unique']) && $indexData['non_unique'] == 1) {
            $options[] = '\'unique\' => false';
        } else {
            $options[] = '\'unique\' => true';
        }
        // MyISAM only
        if (isset($indexData['index_type']) && $indexData['index_type'] == 'FULLTEXT') {
            $options[] = '\'type\' => \'fulltext\'';
        }
        $result = 'array(' . implode(', ', $options) . ')';
        return $result;
    }

    protected function getIndexRemove($table, $indexName)
    {
        $result = sprintf("%s\$this->table(\"%s\")->removeIndexByName('%s');", $this->ind2, $table, $indexName);
        return $result;
    }

    protected function getForeignKeyCreate($table, $fkName)
    {
        $foreignKeys = $this->dba->getForeignKeys($table);
        $fkData = $foreignKeys[$fkName];
        $columns = "'" . $fkData['column_name'] . "'";
        //$tableName = $fkData['referenced_table_name'];
        $referencedTable = "'" . $fkData['referenced_table_name'] . "'";
        $referencedColumns = "'" . $fkData['referenced_column_name'] . "'";
        $options = $this->getForeignKeyOptions($fkData);

        /**
         * $columns, $referencedTable, $referencedColumns = array('id'), $options = array()
         *
         * In $options you can specify on_delete|on_delete = cascade|no_action ..,
         * on_update, constraint = constraint name.
         */
        $output = [];
        #$output[] = sprintf("%sif(\$this->table('%s')->hasIndex('%s')) {", $this->ind2, $table, $indexName);
        #$output[] = sprintf("%s%s", $this->ind, $this->getIndexRemove($table, $indexName));
        #$output[] = sprintf("%s}", $this->ind2);
        $output[] = sprintf("%s\$this->table(\"%s\")->addForeignKey(%s, %s, %s, %s)->save();", $this->ind2, $table, $columns, $referencedTable, $referencedColumns, $options);

        $result = implode($this->nl, $output);
        return $result;
    }

    protected function getForeignKeyOptions($fkData)
    {
        $options = array();
        if (isset($fkData['update_rule'])) {
            $options[] = '\'update\' => "' . $this->getForeignKeyRuleValue($fkData['update_rule']) . '"';
        }
        if (isset($fkData['delete_rule'])) {
            $options[] = '\'delete\' => "' . $this->getForeignKeyRuleValue($fkData['delete_rule']) . '"';
        }
        // @todo 'constraint'
        $result = 'array(' . implode(', ', $options) . ')';
        return $result;
    }

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

    protected function getForeignKeyRemove($table, $indexName)
    {
        $result = sprintf("%s\$this->table(\"%s\")->dropForeignKey('%s');", $this->ind2, $table, $indexName);
        return $result;
    }

    /**
     *
     * @param int $value
     * @return type
     */
    protected function getSetForeignKeyCheck($value)
    {
        return sprintf("%s\$this->execute(\"SET FOREIGN_KEY_CHECKS = %s;\");", $this->ind2, $value);
    }

    /**
     *
     * @param int $value 0 or 1
     * @return type
     */
    protected function getSetUniqueChecks($value)
    {
        return sprintf("%s\$this->execute(\"SET UNIQUE_CHECKS = %s;\");", $this->ind2, $value);
    }

}
