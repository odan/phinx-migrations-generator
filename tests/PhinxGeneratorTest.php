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

    public function testAccountsTable()
    {
        $this->execSql("CREATE TABLE `accounts` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `type` int(11) unsigned NOT NULL,
          `status` int(11) unsigned NOT NULL DEFAULT '1',
          `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
          `pwhash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
          `staff_level` int(11) unsigned NOT NULL DEFAULT '1',
          `display` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
          `logo` tinyint(4) DEFAULT '0',
          PRIMARY KEY (`id`),
          UNIQUE KEY `username_ui` (`username`),
          KEY `type_i` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;");
        $oldSchema = $this->getTableSchema('accounts');
        $this->migrate();

        $newSchema = $this->getTableSchema('accounts');
        $this->assertSame($oldSchema, $newSchema);
    }
}
