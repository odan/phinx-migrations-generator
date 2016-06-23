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
        $diff = $this->read(__DIR__ . '/diffs/newtable.php');
        $actual = $gen->createMigration('MyNewMigration', $diff);
        $expected = file_get_contents(__DIR__ . '/diffs/newtable_expected.php');
        $this->assertEquals($expected, $actual);
    }

    /**
     * Read php file
     *
     * @param string $filename
     * @return mixed
     */
    public function read($filename)
    {
        return require $filename;
    }
}
