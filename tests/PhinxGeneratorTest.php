<?php

namespace Odan\Migration\Test;

use Odan\Migration\Adapter\Database\MySqlAdapter;
use Odan\Migration\Adapter\Generator\PhinxMySqlGenerator;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @coversDefaultClass \Odan\Migration\Adapter\Generator\PhinxMySqlGenerator
 */
class PhinxGeneratorTest extends TestCase
{
    /**
     * Test.
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
        file_put_contents(__DIR__ . '/diffs/actual.php', $actual);

        $expected = file_get_contents(__DIR__ . '/diffs/newtable_expected.php');
        $this->assertEquals($expected, $actual);
    }

    /**
     * Get settings for test database.
     *
     * @return array
     */
    public function getSettings()
    {
        return [
            'dsn' => 'mysql:host=127.0.0.1;dbname=test',
            'username' => 'root',
            'password' => '',
            'options' => [
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8 COLLATE utf8_unicode_ci',
                // Enable exceptions
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Set default fetch mode
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
            'schema_file' => __DIR__ . '/schema.php',
            'foreign_keys' => false,
            'migration_path' => __DIR__,
        ];
    }

    /**
     * Get Db.
     *
     * @param array $settings
     *
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
     * Read php file.
     *
     * @param string $filename
     *
     * @return mixed
     */
    public function read($filename)
    {
        return require $filename;
    }
}
