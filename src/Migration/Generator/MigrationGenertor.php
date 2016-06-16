<?php

namespace Odan\Migration\Generator;

use Odan\Migration\Adapter\Database\DatabaseAdapterInterface;
use Odan\Migration\Adapter\Generator\GeneratorInterface;

/**
 * MigrationGenertor
 */
class MigrationGenertor
{

    public function __construct(DatabaseAdapterInterface $db, GeneratorInterface $generator)
    {

    }

    /**
     * Generate
     * @param array $params
     * @return array Description
     */
    public function generate($params)
    {

    }
}
