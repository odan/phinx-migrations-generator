<?php

use Odan\Migration\Adapter\Database\MySqlAdapter;
use Odan\Migration\Adapter\Generator\PhinxMySqlGenerator;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @coversDefaultClass \Odan\Migration\Adapter\Generator\PhinxMySqlGenerator
 */
class GenerateMigrationTest extends \PHPUnit\Framework\TestCase
{

    /**
     * Test
     *
     * @covers ::createMigration
     * @covers ::addChangeMethod
     * @covers ::getTableMigration
     * @covers ::getTableMigrationNewDatabase
     * @covers ::getTableMigrationNewTables
     * @covers ::getTableMigrationNewTablesColumns
     * @covers ::getTableMigrationOldTablesColumns
     * @covers ::getTableMigrationIndexes
     * @covers ::getTableMigrationOldTables
     * @covers ::appendLines
     * @covers ::getForeignKeysMigrations
     * @covers ::getAlterDatabaseCharset
     * @covers ::getAlterDatabaseCollate
     * @covers ::getCreateTable
     * @covers ::getDropTable
     * @covers ::getAlterTableEngine
     * @covers ::getAlterTableCharset
     * @covers ::getAlterTableCollate
     * @covers ::getAlterTableComment
     * @covers ::getColumnCreate
     * @covers ::getColumnUpdate
     * @covers ::getColumnRemove
     * @covers ::getMySQLColumnType
     * @covers ::getPhinxColumnType
     * @covers ::getPhinxColumnOptions
     * @covers ::getPhinxColumnOptionsDefault
     * @covers ::getPhinxColumnOptionsNull
     * @covers ::getPhinxColumnOptionsTimestamp
     * @covers ::getPhinxColumnOptionsLimit
     * @covers ::getPhinxColumnOptionsComment
     * @covers ::getPhinxColumnOptionsNumeric
     * @covers ::getPhinxColumnOptionsAfter
     * @covers ::getOptionEnumValue
     * @covers ::getColumnLimit
     * @covers ::getIndexCreate
     * @covers ::getIndexFields
     * @covers ::getIndexOptions
     * @covers ::getIndexRemove
     * @covers ::getForeignKeyCreate
     * @covers ::getForeignKeyOptions
     * @covers ::getForeignKeyRuleValue
     * @covers ::getForeignKeyRemove
     * @covers ::getSetForeignKeyCheck
     * @covers ::getSetUniqueChecks
     * @covers ::eq
     * @covers ::neq
     */
    public function testGenerate()
    {
        $settings = $this->getSettings();
        $output = new NullOutput();
        $pdo = $this->getPdo($settings);
        $dba = new MySqlAdapter($pdo, $output);
        $gen = new PhinxMySqlGenerator($dba, $output, $settings);

        $diff = $this->read(__DIR__ . '/diffs/newtable.php');
        $actual = $gen->createMigration('MyNewMigration', $diff, []);
        //file_put_contents(__DIR__ . '/diffs/actual.php', $actual);

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

    /**
     * Get Db
     *
     * @param array $settings
     * @return PDO
     */
    public function getPdo($settings)
    {
        $options = array_replace_recursive($settings['options'], [
            // Enable exceptions
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false,
            // Set default fetch mode
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo = new PDO($settings['dsn'], $settings['username'], $settings['password'], $options);
        return $pdo;
    }

    /**
     * Get settings for test database.
     *
     * @return array
     */
    public function getSettings()
    {
        return array(
            'dsn' => 'mysql:host=127.0.0.1;dbname=test',
            'username' => 'root',
            'password' => '',
            'options' => array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8 COLLATE utf8_unicode_ci',
                // Enable exceptions
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Set default fetch mode
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ),
            'schema_file' => __DIR__ . '/schema.php',
            'foreign_keys' => false,
            'migration_path' => __DIR__
        );
    }
}
