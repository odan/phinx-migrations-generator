<?php

use Odan\Migration\Adapter\Generator\PhinxGenerator;

/**
 * @coversDefaultClass \Odan\Migration\Adapter\Generator\PhinxGenerator
 */
class GenerateMigrationTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Test
     *
     * @covers ::createMigration
     */
    public function testGenerate()
    {
        $gen = new PhinxGenerator();
        $actual = $gen->createMigration(file_get_contents(__DIR__ . '/diffs/newtable.php'));
        $expected = file_get_contents(__DIR__ . '/diffs/newtable_expected.php');
        $this->assertEquals($expected, $actual);
    }
}
