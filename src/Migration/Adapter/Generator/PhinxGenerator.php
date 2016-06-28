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
            }
        }
        return $output;
    }

    public function getCreateTable($table)
    {
        return sprintf("%s\$this->table(\"%s\")->save();", $this->ind2, $table);
    }

    public function getAddColumn($table, $column, $dataType)
    {
        return sprintf("%s\$this->table(\"%s\")->addColumn('%s', '%s', '%s')->save();", $this->ind2, $table, $column, $dataType);
    }

    protected function getIndentation($level)
    {
        return str_repeat('    ', $level);
    }

}
