<?php

namespace Odan\Migration\Adapter\Generator;

//use Exception;

/**
 * PhinxGenerator
 */
class PhinxGenerator implements GeneratorInterface
{

    protected $ind = '    ';
    protected $ind2 = '        ';

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
        return sprintf("%s\$this->execute('ALTER TABLE `%s` ENGINE=%s;');", $this->ind2, $table, $engine);
    }

    protected function getAlterTableComment($table, $comment)
    {
        $commentSave = $this->slash($comment);
        return sprintf("%s\$this->execute('ALTER TABLE `%s` COMMENT='%s';');", $this->ind2, $table, $commentSave);
    }

    /**
     *
     * @param type $string
     * @return type
     */
    public function slash($string)
    {
        /**
         * http://dev.mysql.com/doc/refman/5.7/en/string-literals.html
         * \0	An ASCII NUL (X'00') character
          \'	A single quote (“'”) character
          \"	A double quote (“"”) character
          \b	A backspace character
          \n	A newline (linefeed) character
          \r	A carriage return character
          \t	A tab character
          \Z	ASCII 26 (Control+Z); see note following the table
          \\	A backslash (“\”) character
          \%	A “%” character; see note following the table
          \_
         */
        //return addcslashes($string, '\0\'"\b\n\r\t\Z\\%_');
        return addslashes($string);
    }

    protected function getAddColumn($table, $column, $dataType)
    {
        return sprintf("%s\$this->table(\"%s\")->addColumn('%s', '%s', '%s')->save();", $this->ind2, $table, $column, $dataType);
    }

    protected function getIndentation($level)
    {
        return str_repeat('    ', $level);
    }

}
