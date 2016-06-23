<?php

namespace Odan\Migration\Adapter\Generator;

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
        // todo
        $migration = '<?php return ' . var_export($diffs, true) . ';';
        return $migration;
    }

}
