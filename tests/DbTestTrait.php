<?php

namespace Odan\Migration\Test;

use Odan\Migration\Command\GenerateCommand;
use PDO;
use PDOException;
use Phinx\Console\Command\Migrate;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Trait DbTestTrait.
 */
trait DbTestTrait
{
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var Counter
     */
    public static $counter;

    /**
     * Call this template method before each test method is run.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->setUpDatabase();
    }

    protected function tearDown()
    {
        $files = glob(__DIR__ . '/*_test*.php');
        foreach ($files ?: [] as $file) {
            unlink($file);
        }
    }

    /**
     * Call this template method before each test method is run.
     *
     * @return void
     */
    protected function setUpDatabase()
    {
        //$this->dropDatabase();
        //$this->createDatabase();
        $this->dropTables();
    }

    /**
     * Get settings for test database.
     *
     * @return array
     */
    public function getSettings()
    {
        return [
            'dsn' => 'mysql:host=127.0.0.1;dbname=phinx_test;charset=utf8',
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
     * @return PDO
     */
    public function getPdo(): PDO
    {
        if ($this->pdo) {
            return $this->pdo;
        }

        $settings = $this->getSettings();

        $options = [
            // Enable exceptions
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false,
            // Set default fetch mode
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if (!empty($settings['options'])) {
            $options = array_replace_recursive($settings['options'], $options);
        }

        $this->pdo = new PDO($settings['dsn'], $settings['username'], $settings['password'], $options);

        return $this->pdo;
    }

    protected function createDatabase()
    {
        $this->execSql('CREATE DATABASE `phinx_test` CHARACTER SET utf8 COLLATE utf8_unicode_ci;');
        $this->execSql('USE `phinx_test`');
    }

    protected function dropDatabase()
    {
        $this->execSql('DROP DATABASE IF EXISTS `phinx_test`;');
    }

    /**
     * 2. Clean-Up Database. Truncate tables.
     */
    public function dropTables()
    {
        $sql = 'SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = database()';

        $db = $this->getPdo();

        $db->exec('SET UNIQUE_CHECKS=0;');
        $db->exec('SET FOREIGN_KEY_CHECKS=0;');

        $statement = $db->query($sql);
        $statement->execute();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $db->exec(sprintf('DROP TABLE `%s`;', $row['table_name']));
        }

        $db->exec('SET UNIQUE_CHECKS=1;');
        $db->exec('SET FOREIGN_KEY_CHECKS=1;');
    }

    protected function getTableSchema(string $table): string
    {
        $db = $this->getPdo();
        $sql = sprintf('SHOW CREATE TABLE `%s`;', $table);
        $statement = $db->query($sql);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (empty($row)) {
            throw new RuntimeException(sprintf('Table not found: %s', $table));
        }

        return (string)$row['Create Table'];
    }

    protected function execSql(string $sql)
    {
        $pdo = $this->getPdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $pdo->exec($sql);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '00000') {
                fwrite(STDERR, 'PDO Error: ' . $exception->getMessage() . "\n");
                fwrite(STDERR, $sql . "\n");
            }
        }
    }

    protected function migrate()
    {
        chdir(__DIR__);

        if (file_exists(__DIR__ . '/schema.php')) {
            unlink(__DIR__ . '/schema.php');
        }

        // must be really unique
        $number = date('YmdHisu') . '_' . ++static::$counter . '_' . $this->uuid();

        // generate
        $application = new Application();
        $application->add(new GenerateCommand());

        $command = $application->find('generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(),
            '--name' => 'Test' . $number,
            '--overwrite' => '1',
            '--path' => __DIR__,
        ]);

        // debugging: print content (only for travis-ci)
        /*
        $files = glob(__DIR__ . '/*_test*.php');
        foreach ($files ?: [] as $file) {
            fwrite(STDERR, $file . "\n");
            fwrite(STDERR, file_get_contents($file) . "\n");
        }

        fwrite(STDERR, __DIR__ . '/schema.php' . "\n");
        fwrite(STDERR, file_get_contents(__DIR__ . '/schema.php') . "\n");
        */

        $display = $commandTester->getDisplay();
        $statusCode = $commandTester->getStatusCode();
        if ($statusCode > 0 || !strpos($display, 'Generate migration finished')) {
            throw new RuntimeException('Generate migration failed');
        }

        // Run phinx migrate
        //$this->dropDatabase();
        //$this->createDatabase();

        $phinxApplication = new Application();
        $phinxApplication->add(new Migrate());

        $phinxMigrateCommand = $phinxApplication->find('migrate');
        $phinxCommandTester = new CommandTester($phinxMigrateCommand);
        $phinxCommandTester->execute(['command' => $phinxMigrateCommand->getName()]);

        $phinxDisplay = $phinxCommandTester->getDisplay();
        $phinxStatusCode = $phinxCommandTester->getStatusCode();
        if ($phinxStatusCode > 0 || !strpos($phinxDisplay, 'All Done.')) {
            throw new RuntimeException('Running migration failed');
        }

        return true;
    }

    /**
     * Returns a `UUID` v4 created from a cryptographically secure random value.
     *
     * @see https://www.ietf.org/rfc/rfc4122.txt
     *
     * @throws Exception
     *
     * @return string RFC 4122 UUID
     */
    protected function uuid(): string
    {
        return sprintf(
            '%04x%04x%04x%04x%04x%04x%04x%04x',
            // 32 bits for "time_low"
            random_int(0, 65535),
            random_int(0, 65535),
            // 16 bits for "time_mid"
            random_int(0, 65535),
            // 12 bits before the 0100 of (version) 4 for "time_hi_and_version"
            random_int(0, 4095) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            random_int(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            random_int(0, 65535),
            random_int(0, 65535),
            random_int(0, 65535)
        );
    }
}
