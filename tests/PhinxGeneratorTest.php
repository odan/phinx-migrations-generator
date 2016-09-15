<?php

use PDO;
use Odan\Migration\Adapter\Database\MySqlAdapter;
use Odan\Migration\Adapter\Generator\PhinxMySqlGenerator;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @coversDefaultClass \Odan\Migration\Adapter\Generator\PhinxMySqlGenerator
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
        $output = new NullOutput();
        $pdo = $this->getPdo($this->getSettings());
        $dba = new MySqlAdapter($pdo, $output);
        $gen = new PhinxMySqlGenerator($dba, $output);
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
            // Convert column names to lower case.
            PDO::ATTR_CASE => PDO::CASE_LOWER,
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
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            ),
            'schema_file' => __DIR__ . '/schema.php',
            'migration_path' => __DIR__
        );
    }

}
