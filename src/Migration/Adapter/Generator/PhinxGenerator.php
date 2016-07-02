<?php

namespace Odan\Migration\Adapter\Generator;

use Exception;
use Odan\Migration\Adapter\Database\DatabaseAdapterInterface;
//use Odan\Migration\Adapter\Generator\GeneratorInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
            }
        }
        if (!empty($old['tables'])) {
            foreach ($old['tables'] as $tableName => $table) {
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

    protected function getAddColumn($table, $column, $dataType)
    {
        return sprintf("%s\$this->table(\"%s\")->addColumn('%s', '%s', '%s')->save();", $this->ind2, $table, $column, $dataType);
    }

}
