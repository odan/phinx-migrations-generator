<?php

namespace Odan\Migration\Adapter\Generator;

use Exception;

/**
 * PhinxGenerator
 */
class PhinxGenerator implements GeneratorInterface
{

    /**
     * Create migration
     *
     * @param array $diffs
     * @param int $indent
     * @return string PHP code
     */
    public function createMigration($diffs, $indent = 2)
    {
        // PSR-2: All PHP files MUST use the Unix LF (linefeed) line ending.
        $nl = "\n";
        $old = $diffs[0];
        $new = $diffs[1];

        $output = array();
        $output[] = '<?php';
        $output[] = null;
        $output[] = 'use Phinx\Migration\AbstractMigration;';
        $output[] = null;
        $output[] = 'class MyNewMigration extends AbstractMigration';
        $output[] = '{';
        $output[] = null;
        $output[] = '}';
        $output[] = null;
        $result = implode($nl, $output);
        return $result;
    }
}
