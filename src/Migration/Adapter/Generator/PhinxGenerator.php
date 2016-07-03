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
     * @param \Odan\Migration\Adapter\Database\MySqlAdapter $dba
     * @param \Odan\Migration\Adapter\Generator\OutputInterface $output
     */
    public function __construct(\Odan\Migration\Adapter\Database\MySqlAdapter $dba, OutputInterface $output)
    {
        $this->dba = $dba;
        $this->output = $output;
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
        // PSR-2: All PHP files MUST use the Unix LF (linefeed) line ending.
        $nl = "\n";

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
        $result = implode($nl, $output);
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
                        if ($columnName == 'id') {
                            continue;
                        }
                        if (!isset($old['tables'][$tableName]['columns'][$columnName])) {
                            $output[] = $this->getColumnCreate($tableName, $columnName, $columnData);
                        } else {
                            $output[] = $this->getColumnUpdate($tableName, $columnName, $columnData);
                        }
                    }
                }
            }
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
        $phinxType = $this->getPhinxColumnType($columnData);
        $columnAttributes = $this->getPhinxColumnOptions($phinxType, $columnData);
        $result = sprintf("%s\$this->table(\"%s\")->addColumn('%s', '%s', $columnAttributes)->update();", $this->ind2, $table, $columnName, $phinxType, $columnAttributes);
        return $result;
    }

    protected function getColumnUpdate($table, $columnName, $columnData)
    {
        $columns = $this->dba->getColumns($table);
        $columnData = $columns[$columnName];

        $phinxType = $this->getPhinxColumnType($columnData);
        $columnAttributes = $this->getPhinxColumnOptions($phinxType, $columnData);
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

    function getPhinxColumnType($columnData)
    {
        $columnType = $columnData['column_type'];
        if ($columnType == 'tinyint(1)') {
            return 'boolean';
        }

        $type = $this->getMySQLColumnType($columnData);
        switch ($type) {
            case 'tinyint':
            case 'smallint':
            case 'int':
            case 'mediumint':
                return 'integer';
            case 'timestamp':
                return 'timestamp';
            case 'date':
                return 'date';
            case 'datetime':
                return 'datetime';
            case 'enum':
                return 'enum';
            case 'char':
                return 'char';
            case 'text':
            case 'tinytext':
                return 'text';
            case 'varchar':
                return 'string';
            case 'decimal':
                return 'decimal';
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
     * @return type
     */
    function getPhinxColumnOptions($phinxtype, $columnData)
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
            $attributes[] = '\'values\' => ' . str_replace('enum', 'array', $columnData['column_type']);
        }

        // Set a text comment on the column
        if (!empty($columnData['column_comment'])) {
            $attributes[] = '\'comment\' => "' . addslashes($columnData['column_comment']) . '"';
        }

        //For integer and biginteger columns:
        // identity enable or disable automatic incrementing
        if ($columnData['extra'] == 'auto_increment') {
            $attributes[] = '\'identity\' => \'enable\'';
        }

        // @todo
        // after: specify the column that a new column should be placed after
        //
        //

        // update set an action to be triggered when the row is updated (use with CURRENT_TIMESTAMP)
        // timezone enable or disable the with time zone option for time and timestamp columns (only applies to Postgres)
        //
        // For foreign key definitions:
        // update set an action to be triggered when the row is updated
        // delete set an action to be triggered when the row is deleted

        return 'array(' . implode(', ', $attributes) . ')';
    }

    public function getColumnLimit($columnData)
    {
        if (!empty($columnData['character_maximum_length'])) {
           return $columnData['character_maximum_length'];
        }

        $limit = 0;
        $pattern = '/\((\d+)\)/';
        if (preg_match($pattern, $columnData['column_type'], $match) === 1) {
            return $match[1];
        }

        $type = $this->getMySQLColumnType($columnData);
        switch ($type) {
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
            default:
                $pattern = '/\((\d+)\)/';
                if (preg_match($pattern, $columnData['column_type'], $match) === 1) {
                    $limit = $match[1];
                }
        }
        return $limit;
    }
}
