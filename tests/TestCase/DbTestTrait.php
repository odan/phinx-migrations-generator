<?php

namespace Odan\Migration\Test\TestCase;

use Odan\Migration\Command\GenerateCommand;
use PDO;
use PDOException;
use PDOStatement;
use Phinx\Console\Command\Migrate;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Uid\Uuid;
use UnexpectedValueException;

/**
 * Trait DbTestTrait.
 */
trait DbTestTrait
{
    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var int Counter
     */
    public static $counter;

    /**
     * Call this template method before each test method is run.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->unsetStatsExpiry();
        $this->dropTables();
        $this->deleteTestFiles();
    }

    /**
     * Tear down.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropTables();
        $this->deleteTestFiles();
    }

    /**
     * Delete all temporary test files.
     *
     * @return void
     */
    private function deleteTestFiles(): void
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
    private function setUpDatabase(): void
    {
        $this->dropDatabase();
        $this->createDatabase();
    }

    /**
     * Get settings for test database.
     *
     * @return array
     */
    public function getSettings(): array
    {
        return [
            'dsn' => 'mysql:host=127.0.0.1;dbname=phinx_test;charset=utf8mb4',
            'username' => 'root',
            'password' => isset($_SERVER['GITHUB_ACTION']) ? 'root' : '',
            'options' => [
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
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
     * Get PDO connection.
     *
     * @return PDO The connection
     */
    public function getConnection(): PDO
    {
        if ($this->pdo !== null) {
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

    /**
     * Create database.
     *
     * @return void
     */
    private function createDatabase(): void
    {
        $this->execSql('CREATE DATABASE `phinx_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
        $this->execSql('USE `phinx_test`');
    }

    /**
     * Drop test database.
     *
     * @return void
     */
    private function dropDatabase(): void
    {
        $this->execSql('DROP DATABASE IF EXISTS `phinx_test`;');
    }

    /**
     * Workaround for MySQL 8: update_time not working.
     *
     * https://bugs.mysql.com/bug.php?id=95407
     *
     * @return void
     */
    private function unsetStatsExpiry()
    {
        $expiry = $this->getDatabaseVariable('information_schema_stats_expiry');
        $version = (string)$this->getDatabaseVariable('version');

        if ($expiry !== null && version_compare($version, '8.0.0', '>=')) {
            $this->getConnection()->exec('SET information_schema_stats_expiry=0;');
        }
    }

    /**
     * Get database variable.
     *
     * @param string $variable The variable
     *
     * @return string|null The value
     */
    protected function getDatabaseVariable(string $variable): ?string
    {
        $statement = $this->createPreparedStatement('SHOW VARIABLES LIKE ?');
        if ($statement->execute([$variable]) === false) {
            throw new UnexpectedValueException('Invalid SQL statement');
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            // Database variable not defined
            return null;
        }

        return (string)$row['Value'];
    }

    /**
     * Clean-Up Database. Truncate tables.
     *
     * @return void
     */
    public function dropTables(): void
    {
        $sql = 'SELECT TABLE_NAME
                FROM information_schema.tables
                WHERE table_schema = database()';

        $db = $this->getConnection();

        $db->exec('SET unique_checks=0; SET foreign_key_checks=0;');

        $statement = $db->query($sql);

        if (!$statement) {
            throw new RuntimeException('Invalid statement');
        }

        $statement->execute();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $db->exec(sprintf('DROP TABLE `%s`;', $row['TABLE_NAME']));
        }

        $db->exec('SET unique_checks=1; SET foreign_key_checks=1;');
    }

    /**
     * Check whether table exists.
     *
     * @param string $table The table name
     *
     * @return bool The status
     */
    private function existsTable(string $table): bool
    {
        $sql = 'SELECT TABLE_NAME
                FROM information_schema.tables
                WHERE table_schema = database()
                AND TABLE_NAME = :table_name';

        $statement = $this->createPreparedStatement($sql);
        $statement->execute(['table_name' => $table]);
        $row = $statement->fetch();

        return !empty($row);
    }

    /**
     * Get table schema.
     *
     * @param string $table The table name
     *
     * @return string The schema sql
     */
    private function getTableSchema(string $table): string
    {
        $sql = sprintf('SHOW CREATE TABLE `%s`;', $table);
        $statement = $this->createQueryStatement($sql);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (empty($row)) {
            throw new RuntimeException(sprintf('Table not found: %s', $table));
        }

        return (string)$row['Create Table'];
    }

    /**
     * Create a new PDO statement.
     *
     * @param string $sql The sql
     *
     * @throws UnexpectedValueException
     *
     * @return PDOStatement The statement
     */
    private function createQueryStatement(string $sql): PDOStatement
    {
        $statement = $this->getConnection()->query($sql);

        if (!$statement instanceof PDOStatement) {
            throw new UnexpectedValueException('Invalid statement');
        }

        return $statement;
    }

    /**
     * Create a new PDO statement.
     *
     * @param string $sql The sql
     *
     * @throws UnexpectedValueException
     *
     * @return PDOStatement The statement
     */
    private function createPreparedStatement(string $sql): PDOStatement
    {
        $statement = $this->getConnection()->prepare($sql);

        if (!$statement instanceof PDOStatement) {
            throw new UnexpectedValueException('Invalid statement');
        }

        return $statement;
    }

    /**
     * Execute sql.
     *
     * @param string $sql The sql
     *
     * @return void
     */
    private function execSql(string $sql): void
    {
        $pdo = $this->getConnection();
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

    private function generateAgain(): bool
    {
        return $this->generate(false);
    }

    private function generate(bool $deleteSchema = true): bool
    {
        chdir(__DIR__);

        if ($deleteSchema === true && file_exists(__DIR__ . '/schema.php')) {
            unlink(__DIR__ . '/schema.php');
        }

        if ($this->existsTable('phinxlog')) {
            // wait because the phinxlog.id must be unique (format: YmdHis)
            sleep(1);
        }

        // must be unique and camel-case
        $number = date('YmdHisu') . '_' . ++static::$counter . '_' .
            (new UnicodeString(Uuid::v4()->toRfc4122()))->camel()->toString();

        // generate
        $application = new Application();
        $application->add(new GenerateCommand());

        $command = $application->find('generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
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

        return true;
    }

    public function migrate(): bool
    {
        chdir(__DIR__);

        $phinxApplication = new Application();
        $phinxApplication->add(new Migrate());

        $phinxMigrateCommand = $phinxApplication->find('migrate');
        $phinxCommandTester = new CommandTester($phinxMigrateCommand);
        $phinxCommandTester->execute(['command' => $phinxMigrateCommand->getName()]);

        $phinxDisplay = $phinxCommandTester->getDisplay();
        $phinxStatusCode = $phinxCommandTester->getStatusCode();
        if ($phinxStatusCode > 0 || !strpos($phinxDisplay, 'All Done.')) {
            throw new RuntimeException('Running migration failed.' . "\n" . $phinxDisplay);
        }

        return true;
    }
}
