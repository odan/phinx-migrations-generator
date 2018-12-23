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

    /**
     * Test.
     *
     * @return void
     */
    public function testCreateTable(): void
    {
        $this->execSql('CREATE TABLE `table1` (`id` int(11) NOT NULL AUTO_INCREMENT,
              PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC');
        $oldSchema = $this->getTableSchema('table1');
        $this->runGenerateAndMigrate();

        $newSchema = $this->getTableSchema('table1');
        $this->assertSame($oldSchema, $newSchema);
    }

    /**
     * Test.
     *
     * @return void
     */
    public function testCreateTable2(): void
    {
        $this->execSql('CREATE TABLE `table2` (`id` int(11) NOT NULL AUTO_INCREMENT,
              PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC');
        $oldSchema = $this->getTableSchema('table2');
        $this->runGenerateAndMigrate();

        $newSchema = $this->getTableSchema('table2');
        $this->assertSame($oldSchema, $newSchema);
    }

    /**
     * Test.
     *
     * @return void
     */
    public function testRemoveIndex(): void
    {
        $this->execSql('CREATE TABLE `table3` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `field` int(11) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `field` (`field`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC');

        $oldSchema = $this->getTableSchema('table3');
        $this->runGenerateAndMigrate();

        $newSchema = $this->getTableSchema('table3');
        $this->assertSame($oldSchema, $newSchema);
    }

    /**
     * Test.
     *
     * @return void
     */
    public function testIndexWithMultipleFields(): void
    {
        $this->execSql('CREATE TABLE `table4` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `field` int(11) DEFAULT NULL,
              `field2` int(11) DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC');

        $this->runGenerateAndMigrate();

        $this->execSql('ALTER TABLE `table4` ADD INDEX `indexname` (`field`, `field2`); ');
        $oldSchema = $this->getTableSchema('table4');
        $this->runGenerateAndMigrate(false);

        $newSchema = $this->getTableSchema('table4');
        $this->assertSame($oldSchema, $newSchema);
    }

    /**
     * Test. #46.
     *
     * @return void
     */
    public function testIndexWithMultiplePkFields(): void
    {
        $this->execSql('CREATE TABLE `test` (
            `pk1` int(11) unsigned NOT NULL,
            `pk2` int(11) unsigned NOT NULL,
            PRIMARY KEY (`pk1`,`pk2`)
            ) ENGINE=InnoDB');

        $this->runGenerateAndMigrate();

        $this->execSql('ALTER TABLE `test` ADD INDEX `indexname` (`pk1`, `pk2`); ');
        $oldSchema = $this->getTableSchema('test');
        $this->runGenerateAndMigrate(false);

        $newSchema = $this->getTableSchema('test');
        $this->assertSame($oldSchema, $newSchema);

        $this->execSql('ALTER TABLE `test` DROP INDEX `indexname`;');
        $oldSchema = $this->getTableSchema('test');
        $this->runGenerateAndMigrate(false);

        $newSchema = $this->getTableSchema('test');
        $this->assertSame($oldSchema, $newSchema);
    }

    /**
     * Test. #46.
     *
     * @return void
     */
    public function testEnum(): void
    {
        $this->execSql("CREATE TABLE `test`( 
            `id` INT(11) NOT NULL AUTO_INCREMENT, 
            `simple_value` ENUM('1'), 
            `multiple_values` ENUM('1','2','3','abc'), 
            PRIMARY KEY (`id`) );
        ");

        $this->runGenerateAndMigrate();

        $this->execSql("ALTER TABLE `test` CHANGE `multiple_values` `multiple_values` ENUM('1','2');");
        $oldSchema = $this->getTableSchema('test');
        $this->runGenerateAndMigrate(false);

        $newSchema = $this->getTableSchema('test');
        $this->assertSame($oldSchema, $newSchema);
    }
}
