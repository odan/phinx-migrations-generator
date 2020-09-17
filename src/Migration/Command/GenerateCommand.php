<?php

namespace Odan\Migration\Command;

use InvalidArgumentException;
use Odan\Migration\Adapter\Database\MySqlSchemaAdapter;
use Odan\Migration\Adapter\Database\SchemaAdapterInterface;
use Odan\Migration\Generator\MigrationGenerator;
use PDO;
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
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws InvalidArgumentException
     *
     * @return int integer 0 on success, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap($input, $output);

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

        $envOptions = $this->getConfig()->getEnvironment($environment);
        if (isset($envOptions['adapter']) && !$this->isAdapterSupported($envOptions['adapter'])) {
            $output->writeln('<error>adapter not supported</error> ' . $envOptions['adapter']);

            return 1;
        }
        if (isset($envOptions['name'])) {
            $output->writeln('<info>using database</info> ' . $envOptions['name']);
        } else {
            $output->writeln('<error>Could not determine database name! Please specify a database name in your config file.</error>');

            return 1;
        }

        $settings = $this->getGeneratorSettings($input, $environment);

        $output->writeln('<info>using config file</info> ' . ($settings['config_file'] ?? null));
        $output->writeln('<info>using migration path</info> ' . ($settings['migration_path'] ?? null));
        $output->writeln('<info>using schema file</info> ' . ($settings['schema_file'] ?? null));

        $generator = $this->getMigrationGenerator($settings, $input, $output, $environment);

        return $generator->generate();
    }

    /**
     * @param string $adapterName
     *
     * @return bool true if adapter with specified name is supported
     */
    private function isAdapterSupported(string $adapterName): bool
    {
        return $adapterName === 'mysql';
    }

    /**
     * @param string $migrationsPath
     *
     * @return string Schema file path
     */
    private function getDefaultSchemaFilePath(string $migrationsPath): string
    {
        return $migrationsPath . DIRECTORY_SEPARATOR . 'schema.php';
    }

    /**
     * Get MigrationGenerator instance.
     *
     * @param array $settings
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $environment
     *
     * @throws UnexpectedValueException
     *
     * @return MigrationGenerator
     */
    private function getMigrationGenerator(array $settings, InputInterface $input, OutputInterface $output, string $environment): MigrationGenerator
    {
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
     * @param PDO $pdo
     * @param OutputInterface $output
     *
     * @return SchemaAdapterInterface
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
     * @return array
     */
    private function getGeneratorSettings(InputInterface $input, string $environment): array
    {
        $envOptions = $this->getConfig()->getEnvironment($environment);

        // Load config and database adapter
        $manager = $this->getManager();

        if (!$manager) {
            throw new UnexpectedValueException('Manager not found');
        }

        $config = $manager->getConfig();

        $configFilePath = $config->getConfigFilePath();

        // First, try the non-interactive option:
        $migrationsPaths = $input->getOption('path');
        if (empty($migrationsPaths)) {
            $migrationsPaths = $config->getMigrationPaths();
        }

        $migrationsPaths = is_array($migrationsPaths) ? $migrationsPaths : (array)$migrationsPaths;

        // No paths? That's a problem.
        if (empty($migrationsPaths)) {
            throw new UnexpectedValueException('No migration paths set in your Phinx configuration file.');
        }

        $key = array_key_first($migrationsPaths);

        $migrationsPath = (string)$migrationsPaths[$key];
        $this->verifyMigrationDirectory($migrationsPath);

        $schemaFile = $config->offsetExists('schema_file') ? $config->offsetGet('schema_file') : false;
        if (!$schemaFile) {
            $schemaFile = $this->getDefaultSchemaFilePath($migrationsPath);
        }

        // Gets the database adapter.
        $dbAdapter = $manager->getEnvironment($environment)->getAdapter();

        $pdo = $this->getPdo($manager, $environment);

        $foreignKeys = $config->offsetExists('foreign_keys') ? $config->offsetGet('foreign_keys') : false;
        $defaultMigrationPrefix = $config->offsetExists('default_migration_prefix') ? $config->offsetGet('default_migration_prefix') : null;
        $generateMigrationName = $config->offsetExists('generate_migration_name') ? $config->offsetGet('generate_migration_name') : false;
        $markMigration = $config->offsetExists('mark_generated_migration') ? $config->offsetGet('mark_generated_migration') : true;

        $defaultMigrationTable = $envOptions['default_migration_table'] ?? 'phinxlog';

        $name = $input->getOption('name');
        $overwrite = $input->getOption('overwrite');

        return [
            'pdo' => $pdo,
            'manager' => $manager,
            'environment' => $environment,
            'adapter' => $dbAdapter,
            'schema_file' => $schemaFile,
            'migration_path' => $migrationsPaths[$key],
            'foreign_keys' => $foreignKeys,
            'config_file' => $configFilePath,
            'name' => $name,
            'overwrite' => $overwrite,
            'mark_migration' => $markMigration,
            'default_migration_table' => $defaultMigrationTable,
            'default_migration_prefix' => $defaultMigrationPrefix,
            'generate_migration_name' => $generateMigrationName,
            'migration_base_class' => $config->getMigrationBaseClassName(false),
            'namespace' => $config instanceof NamespaceAwareInterface ? $config->getMigrationNamespaceByPath($migrationsPaths[$key]) : null,
        ];
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
