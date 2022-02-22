<?php

namespace Odan\Migration\Command;

use InvalidArgumentException;
use Odan\Migration\Adapter\Database\MySqlSchemaAdapter;
use Odan\Migration\Adapter\Database\SchemaAdapterInterface;
use Odan\Migration\Generator\MigrationGenerator;
use PDO;
use Phinx\Config\ConfigInterface;
use Phinx\Config\NamespaceAwareInterface;
use Phinx\Console\Command\AbstractCommand;
use Phinx\Db\Adapter\AdapterWrapper;
use Phinx\Db\Adapter\PdoAdapter;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

/**
 * Generate Command.
 */
final class GenerateCommand extends AbstractCommand
{
    /**
     * Configure.
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('environment', 'e', InputOption::VALUE_REQUIRED, 'The target environment.');

        $this->setName('generate');
        $this->setDescription('Generate a new migration');

        // Allow the migration path to be chosen non-interactively.
        $this->addOption(
            'path',
            null,
            InputOption::VALUE_REQUIRED,
            'Specify the path in which to generate this migration'
        );

        $this->addOption(
            'name',
            null,
            InputOption::VALUE_REQUIRED,
            'Specify the name of the migration for this migration'
        );

        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite schema file');
    }

    /**
     * Generate migration.
     *
     * @param InputInterface $input The input
     * @param OutputInterface $output The output
     *
     * @throws InvalidArgumentException
     *
     * @return int The value 0 on success, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap($input, $output);

        $environmentName = $this->getEnvironmentName($input, $output);
        if (!$this->checkEnvironmentSettings($environmentName, $output)) {
            return 1;
        }

        $settings = $this->getGeneratorSettings($input, $environmentName);

        $output->writeln('<info>using config file</info> ' . ($settings['config_file'] ?? null));
        $output->writeln('<info>using migration path</info> ' . ($settings['migration_path'] ?? null));
        $output->writeln('<info>using schema file</info> ' . ($settings['schema_file'] ?? null));

        $generator = $this->getMigrationGenerator($settings, $input, $output, $environmentName);

        return $generator->generate();
    }

    /**
     * Get invironment name.
     *
     * @param InputInterface $input The input
     * @param OutputInterface $output The output
     *
     * @return string The name
     */
    private function getEnvironmentName(InputInterface $input, OutputInterface $output): string
    {
        $environment = $input->getOption('environment');
        $environment = is_scalar($environment) ? (string)$environment : null;

        if ($environment === null) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        if (!$environment) {
            throw new InvalidArgumentException('Invalid or missing environment');
        }

        return $environment;
    }

    /**
     * Check settings.
     *
     * @param string $environmentName The env name
     * @param OutputInterface $output The output
     *
     * @return bool The status
     */
    private function checkEnvironmentSettings(string $environmentName, OutputInterface $output): bool
    {
        $environmentOptions = $this->getConfig()->getEnvironment($environmentName);

        if (isset($environmentOptions['adapter']) && !$this->isAdapterSupported($environmentOptions['adapter'])) {
            $output->writeln('<error>adapter not supported</error> ' . $environmentOptions['adapter']);

            return false;
        }

        if (isset($environmentOptions['name'])) {
            $output->writeln('<info>using database</info> ' . $environmentOptions['name']);
        } else {
            $output->writeln(
                '<error>Could not determine database name! Please specify a database name in your config file.</error>'
            );

            return false;
        }

        return true;
    }

    /**
     * Is adapter supported.
     *
     * @param string $adapterName The adapter name
     *
     * @return bool The value true if adapter with specified name is supported
     */
    private function isAdapterSupported(string $adapterName): bool
    {
        return $adapterName === 'mysql';
    }

    /**
     * Get default schema path.
     *
     * @param string $migrationsPath The path
     *
     * @return string The schema file path
     */
    private function getDefaultSchemaFilePath(string $migrationsPath): string
    {
        return $migrationsPath . DIRECTORY_SEPARATOR . 'schema.php';
    }

    /**
     * Get MigrationGenerator instance.
     *
     * @param array $settings The settings
     * @param InputInterface $input The input
     * @param OutputInterface $output The output
     * @param string $environment The env
     *
     * @throws UnexpectedValueException
     *
     * @return MigrationGenerator The generator
     */
    private function getMigrationGenerator(
        array $settings,
        InputInterface $input,
        OutputInterface $output,
        string $environment
    ): MigrationGenerator {
        $manager = $this->getManager();

        if (!$manager) {
            throw new UnexpectedValueException('Manager not found');
        }

        $pdo = $this->getPdo($manager, $environment);
        $schemaAdapter = $this->getSchemaAdapter($pdo, $output);

        return new MigrationGenerator($settings, $input, $output, $schemaAdapter);
    }

    /**
     * Get SchemaAdapter instance.
     *
     * @param PDO $pdo The database connection
     * @param OutputInterface $output The output
     *
     * @return SchemaAdapterInterface The schema
     */
    private function getSchemaAdapter(PDO $pdo, OutputInterface $output): SchemaAdapterInterface
    {
        return new MySqlSchemaAdapter($pdo, $output);
    }

    /**
     * Get settings array.
     *
     * @param InputInterface $input
     * @param string $environment
     *
     * @throws UnexpectedValueException On error
     *
     * @return array The settings
     */
    private function getGeneratorSettings(InputInterface $input, string $environment): array
    {
        // Load config and database adapter
        $manager = $this->getManager();

        if (!$manager) {
            throw new UnexpectedValueException('Manager not found');
        }

        $config = $manager->getConfig();
        $envOptions = $config->getEnvironment($environment);
        $configFilePath = $config->getConfigFilePath();
        $migrationsPath = $this->getMigrationPath($input, $config);
        $schemaFile = $config['schema_file'] ?? $this->getDefaultSchemaFilePath($migrationsPath);
        $dbAdapter = $manager->getEnvironment($environment)->getAdapter();
        $pdo = $this->getPdo($manager, $environment);
        $foreignKeys = $config['foreign_keys'] ?? false;
        $defaultMigrationPrefix = $config['default_migration_prefix'] ?? null;
        $generateMigrationName = $config['generate_migration_name'] ?? false;
        $markMigration = $config['mark_generated_migration'] ?? true;
        $defaultMigrationTable = $envOptions['default_migration_table'] ?? 'phinxlog';
        $namespace = $config instanceof NamespaceAwareInterface ?
            $config->getMigrationNamespaceByPath($migrationsPath) :
            null;

        return [
            'pdo' => $pdo,
            'manager' => $manager,
            'environment' => $environment,
            'adapter' => $dbAdapter,
            'schema_file' => $schemaFile,
            'migration_path' => $migrationsPath,
            'foreign_keys' => (bool)$foreignKeys,
            'config_file' => $configFilePath,
            'name' => $input->getOption('name'),
            'overwrite' => $input->getOption('overwrite'),
            'mark_migration' => $markMigration,
            'default_migration_table' => $defaultMigrationTable,
            'default_migration_prefix' => $defaultMigrationPrefix,
            'generate_migration_name' => $generateMigrationName,
            'migration_base_class' => $config->getMigrationBaseClassName(false),
            'namespace' => $namespace,
        ];
    }

    /**
     * Get migration path.
     *
     * @param InputInterface $input The input
     * @param ConfigInterface $config The config
     *
     * @throws UnexpectedValueException
     *
     * @return string The path
     */
    private function getMigrationPath(InputInterface $input, ConfigInterface $config): string
    {
        // First, try the non-interactive option:
        $migrationsPaths = $input->getOption('path');
        if (empty($migrationsPaths)) {
            $migrationsPaths = $config->getMigrationPaths();
        }

        $migrationsPaths = (array)$migrationsPaths;

        // No paths? That's a problem.
        if (empty($migrationsPaths)) {
            throw new UnexpectedValueException('No migration paths set in your Phinx configuration file.');
        }

        $key = array_key_first($migrationsPaths);
        $migrationsPath = (string)$migrationsPaths[$key];
        $this->verifyMigrationDirectory($migrationsPath);

        return $migrationsPath;
    }

    /**
     * Get PDO instance.
     *
     * @param Manager $manager Manager
     * @param string $environment Environment name
     *
     * @throws UnexpectedValueException On error
     *
     * @return PDO PDO object
     */
    private function getPdo(Manager $manager, string $environment): PDO
    {
        $pdo = null;

        /* @var AdapterWrapper $dbAdapter */
        $dbAdapter = $manager->getEnvironment($environment)->getAdapter();

        if ($dbAdapter instanceof PdoAdapter) {
            $pdo = $dbAdapter->getConnection();
        } elseif ($dbAdapter instanceof AdapterWrapper) {
            $dbAdapter->connect();
            $pdo = $dbAdapter->getAdapter()->getConnection();
        }

        if ($pdo === null) {
            $pdo = $dbAdapter->getOption('connection');
        }

        if (!$pdo instanceof PDO) {
            throw new UnexpectedValueException('PDO database connection not found.');
        }

        return $pdo;
    }
}
