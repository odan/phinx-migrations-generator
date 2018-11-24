<?php

namespace Odan\Migration\Test;

use Odan\Migration\Adapter\Database\MySqlAdapter;
use Odan\Migration\Adapter\Generator\PhinxMySqlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @coversDefaultClass \Odan\Migration\Adapter\Generator\PhinxMySqlGenerator
 */
class PhinxGeneratorTest extends TestCase
{
    use DbTestTrait;

    /**
     * Test.
     */
    public function testGenerate()
    {
        $settings = $this->getSettings();
        $output = new NullOutput();
        $pdo = $this->getPdo();
        $dba = new MySqlAdapter($pdo, $output);
        $gen = new PhinxMySqlGenerator($dba, $output, $settings);

        $diff = $this->read(__DIR__ . '/diffs/newtable.php');
        $actual = $gen->createMigration('MyNewMigration', $diff, []);
        file_put_contents(__DIR__ . '/diffs/actual.php', $actual);

        $expected = file_get_contents(__DIR__ . '/diffs/newtable_expected.php');
        $this->assertEquals($expected, $actual);
    }

    /**
     * Read php file.
     *
     * @param string $filename
     *
     * @return mixed
     */
    protected function read($filename)
    {
        return require $filename;
    }

    public function testCreateTable()
    {
        $this->execSql('CREATE TABLE `table1` (`id` int(11) NOT NULL AUTO_INCREMENT,
              PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC');
        $oldSchema = $this->getTableSchema('table1');
        $this->migrate();

        $newSchema = $this->getTableSchema('table1');
        $this->assertSame($oldSchema, $newSchema);
    }

    public function testCreateTable2()
    {
        $this->execSql('CREATE TABLE `table2` (`id` int(11) NOT NULL AUTO_INCREMENT,
              PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC');
        $oldSchema = $this->getTableSchema('table2');
        $this->migrate();

        $newSchema = $this->getTableSchema('table2');
        $this->assertSame($oldSchema, $newSchema);
    }

    public function testRemoveIndex()
    {
        $this->execSql('CREATE TABLE `table1` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `field` int(11) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `field` (`field`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC');

        $oldSchema = $this->getTableSchema('table1');
        $this->migrate();

        $newSchema = $this->getTableSchema('table1');
        $this->assertSame($oldSchema, $newSchema);
    }

    public function testIndexWithMultipleFields()
    {
        $this->execSql('CREATE TABLE `table1` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `field` int(11) DEFAULT NULL,
              `field2` int(11) DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC');

        // $oldSchema = $this->getTableSchema('table1');
        $this->migrate();

        $this->execSql('ALTER TABLE `table1` ADD INDEX `indexname` (`field`, `field2`); ');
        $oldSchema = $this->getTableSchema('table1');
        $this->migrate();

        $newSchema = $this->getTableSchema('table1');
        $this->assertSame($oldSchema, $newSchema);
    }
}
