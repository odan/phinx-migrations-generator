<?php

namespace Odan\Migration\Test;

use Odan\Migration\Adapter\Database\MySqlSchemaAdapter;
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
        $dba = new MySqlSchemaAdapter($pdo, $output);
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
              PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT');
        $oldSchema = $this->getTableSchema('table1');
        $this->generate();

        $this->dropTables();
        $this->migrate();
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
              PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT');
        $oldSchema = $this->getTableSchema('table2');
        $this->generate();

        $this->dropTables();
        $this->migrate();
        $newSchema = $this->getTableSchema('table2');
        $this->assertSame($oldSchema, $newSchema);
    }

    /**
     * Test.
     *
     * @return void
     */
    public function testCreateTableWithNonNullColumnAsDefault(): void
    {
        $this->execSql("CREATE TABLE `table3` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email_verified` char(1) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'N',
          `password` char(60) COLLATE utf8_unicode_ci NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT");

        $oldSchema = $this->getTableSchema('table3');
        $this->generate();

        $this->dropTables();
        $this->migrate();
        $newSchema = $this->getTableSchema('table3');
        $this->assertSame($oldSchema, $newSchema);
    }

    /**
     * Test.
     *
     * @return void
     */
    public function testCreateTableWithDecimalColumn(): void
    {
        $this->execSql('CREATE TABLE `table3` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `value1` decimal(10,0) DEFAULT NULL,
          `value2` decimal(15,2) DEFAULT NULL,
          `value3` decimal(19,4) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT');

        $oldSchema = $this->getTableSchema('table3');
        $this->generate();

        $this->dropTables();
        $this->migrate();
        $newSchema = $this->getTableSchema('table3');
        $this->assertSame($oldSchema, $newSchema);
    }

    /**
     * Test.
     *
     * @return void
     */
    public function testCreateTableWithIntColumns(): void
    {
        $this->execSql('CREATE TABLE `table_int` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `value1` int(10) DEFAULT NULL,
            `value2` int(1) DEFAULT NULL,
            `value3` tinyint(4) DEFAULT 0,
            `value4` tinyint(1) DEFAULT 0,
            `value5` bigint(20) DEFAULT NULL,
            # not supported in phinx
            # `value6` bigint(19) DEFAULT NULL,
            # `value7` bigint(21) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT');

        $oldSchema = $this->getTableSchema('table_int');
        $this->generate();

        $this->dropTables();
        $this->migrate();
        $newSchema = $this->getTableSchema('table_int');
        $this->assertSame($oldSchema, $newSchema);
    }

    /**
     * Test.
     *
     * @return void
     */
    public function testRemoveColumn(): void
    {
        $this->execSql('CREATE TABLE `table1` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `field2` INT,
            PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT');
        $this->generate();

        $this->execSql('ALTER TABLE `table1` DROP COLUMN `field2`; ');
        $oldSchema = $this->getTableSchema('table1');
        $this->generateAgain();

        $this->dropTables();
        $this->migrate();
        $newSchema = $this->getTableSchema('table1');
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT');

        $oldSchema = $this->getTableSchema('table3');
        $this->generate();

        $this->dropTables();
        $this->migrate();
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT');

        $this->generate();

        $this->execSql('ALTER TABLE `table4` ADD INDEX `indexname` (`field`, `field2`); ');
        $oldSchema = $this->getTableSchema('table4');
        $this->generateAgain();

        $this->dropTables();
        $this->migrate();
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
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;');

        $this->generate();

        $this->execSql('ALTER TABLE `test` ADD INDEX `indexname` (`pk1`, `pk2`); ');
        $oldSchema = $this->getTableSchema('test');
        $this->generateAgain();

        $newSchema = $this->getTableSchema('test');
        $this->assertSame($oldSchema, $newSchema);

        $this->execSql('ALTER TABLE `test` DROP INDEX `indexname`;');
        $oldSchema = $this->getTableSchema('test');
        $this->generateAgain();

        // Reset
        $this->dropTables();

        // Run all generated migrations
        $this->migrate();

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
            PRIMARY KEY (`id`)) ROW_FORMAT=COMPACT;");

        $this->generate();

        $this->execSql("ALTER TABLE `test` CHANGE `multiple_values` `multiple_values` ENUM('1','2');");
        $oldSchema = $this->getTableSchema('test');
        $this->generateAgain();

        // Reset
        $this->dropTables();

        // Run all generated migrations
        $this->migrate();

        $newSchema = $this->getTableSchema('test');
        $this->assertSame($oldSchema, $newSchema);
    }

    /**
     * Test. #46.
     *
     * @return void
     */
    public function testForeignKey(): void
    {
        $this->execSql('CREATE TABLE `table1` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `table2_id` int(11) DEFAULT NULL,
              PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT');

        $this->execSql('CREATE TABLE `table2` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT');

        // 1. migration
        $this->generate();

        $this->execSql('ALTER TABLE `table1` ADD CONSTRAINT `table2_id` 
            FOREIGN KEY (`table2_id`) 
            REFERENCES `table2`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION; ');

        $oldSchema = $this->getTableSchema('table1');
        $oldSchema2 = $this->getTableSchema('table2');

        // 2. migration
        $this->generateAgain();

        // Reset
        $this->dropTables();

        // Run all generated migrations
        $this->migrate();

        // Compare schemas
        $newSchema = $this->getTableSchema('table1');
        $this->assertSame($oldSchema, $newSchema);

        $newSchema2 = $this->getTableSchema('table2');
        $this->assertSame($oldSchema2, $newSchema2);
    }
}
