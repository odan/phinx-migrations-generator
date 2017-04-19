<?php

namespace Odan\Migration\Command;

use Exception;
use Phinx\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Odan\Migration\Generator\MigrationGenerator;

class GenerateCommand extends AbstractCommand
{

    /**
     * Configure
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('environment', 'e', InputOption::VALUE_REQUIRED, 'The target environment.');

        $this->setName('generate');
        $this->setDescription('Generate a new migration');

        // Allow the migration path to be chosen non-interactively.
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Specify the path in which to generate this migration');

        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Specify the name of the migration for this migration');

        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite schema.php file');
    }

    /**
     * Generate migration.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int integer 0 on success, or an error code.
     *
     * @throws Exception On Error
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $environment = $input->getOption('environment');

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }
        $envOptions = $this->getConfig()->getEnvironment($environment);
        if (isset($envOptions['adapter']) && $envOptions['adapter'] != 'mysql') {
            $output->writeln('<error>adapter not supported</error> ' . $envOptions['adapter']);
            return 1;
        }
        if (isset($envOptions['name'])) {
            $output->writeln('<info>using database</info> ' . $envOptions['name']);
        } else {
            $output->writeln('<error>Could not determine database name! Please specify a database name in your config file.</error>');
            return 1;
        }

        // Load config and database adapter
        $manager = $this->getManager();
        $config = $manager->getConfig();

        $configFilePath = $config->getConfigFilePath();
        $output->writeln('<info>using config file</info> ' . $configFilePath);

        // First, try the non-interactive option:
        $migrationsPaths = (array)$input->getOption('path');
        if (empty($migrationsPaths)) {
            $migrationsPaths = $config->getMigrationPaths();
        }
        // No paths? That's a problem.
        if (empty($migrationsPaths)) {
            throw new \Exception('No migration paths set in your Phinx configuration file.');
        }

        $migrationsPath = $migrationsPaths[0];
        $this->verifyMigrationDirectory($migrationsPath);

        $output->writeln('<info>using migration path</info> ' . $migrationsPath);

        $schemaFile = $migrationsPath . DIRECTORY_SEPARATOR . 'schema.php';
        $output->writeln('<info>using schema file</info> ' . $schemaFile);

        // Gets the database adapter.
        $dbAdapter = $manager->getEnvironment($environment)->getAdapter();
        $pdo = $dbAdapter->getConnection();

        $name = $input->getOption('name');
        $overwrite = $input->getOption('overwrite');

        $settings = array(
            'pdo' => $pdo,
            'manager' => $manager,
            'environment' => $environment,
            'adapter' => $dbAdapter,
            'schema_file' => $schemaFile,
            'migration_path' => $migrationsPaths[0],
            'foreign_keys' => 1,
            'config_file' => $configFilePath,
            'name' => $name,
            'overwrite' => $overwrite,
            'mark_migration' => true
        );
        //var_dump($settings);

        $generator = new MigrationGenerator($settings, $input, $output);
        return $generator->generate();
    }
}
