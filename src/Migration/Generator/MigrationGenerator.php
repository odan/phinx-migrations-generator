<?php

namespace Odan\Migration\Generator;

use InvalidArgumentException;
use Odan\Migration\Adapter\Database\SchemaAdapterInterface;
use Odan\Migration\Adapter\Generator\PhinxMySqlGenerator;
use Odan\Migration\Utility\ArrayUtil;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Util\Util;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * MigrationGenerator.
 */
final class MigrationGenerator
{
    /**
     * Settings.
     *
     * @var array
     */
    private $settings;

    /**
     * Database adapter.
     *
     * @var SchemaAdapterInterface
     */
    private $dba;

    /**
     * Generator.
     *
     * @var PhinxMySqlGenerator
     */
    private $generator;

    /**
     * Console output.
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * Console style.
     *
     * @var SymfonyStyle
     */
    private $io;

    /**
     * Constructor.
     *
     * @param array $settings
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param SchemaAdapterInterface $dba
     */
    public function __construct(
        array $settings,
        InputInterface $input,
        OutputInterface $output,
        SchemaAdapterInterface $dba
    ) {
        $this->settings = $settings;
        $this->dba = $dba;
        $this->generator = new PhinxMySqlGenerator($this->dba, $settings);
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * Generates random name, based on "default_migration_prefix" setting.
     *
     * @return string
     */
    private function generateDefaultMigrationName(): string
    {
        if (isset($this->settings['default_migration_prefix'])) {
            return $this->settings['default_migration_prefix'] . uniqid((string)mt_rand(), false);
        }

        return '';
    }

    /**
     * Load current database schema.
     *
     * @return array
     */
    public function getSchema(): array
    {
        return $this->dba->getSchema();
    }

    /**
     * Generate.
     *
     * @throws InvalidArgumentException
     *
     * @return int Status
     */
    public function generate(): int
    {
        $schema = $this->getSchema();
        $oldSchema = $this->getOldSchema($this->settings);
        $diffs = $this->compareSchema($schema, $oldSchema);

        if (empty($diffs[0]) && empty($diffs[1])) {
            $this->output->writeln('No database changes detected.');

            return 1;
        }

        $name = $this->getMigrationName();

        if (empty($name)) {
            $this->output->writeln('Aborted');

            return 1;
        }

        $path = $this->settings['migration_path'];
        $className = $this->createClassName($name);

        if (!Util::isValidPhinxClassName($className)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The migration class name "%s" is invalid. Please use CamelCase format.',
                    $className
                )
            );
        }

        if (!Util::isUniqueMigrationClassName($className, $path)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The migration class name "%s" already exists',
                    $className
                )
            );
        }

        // Compute the file path
        $fileName = Util::mapClassNameToFileName($className);
        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($filePath)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The file "%s" already exists',
                    $filePath
                )
            );
        }

        $migration = $this->generator->createMigration($className, $schema, $oldSchema);
        $this->saveMigrationFile($filePath, $migration);

        // Mark migration as as completed
        if (!empty($this->settings['mark_migration'])) {
            $this->markMigration($className, $fileName);
        }

        // Overwrite schema file
        // http://symfony.com/blog/new-in-symfony-2-8-console-style-guide
        if (!empty($this->settings['overwrite'])) {
            $overwrite = 'y';
        } else {
            $overwrite = $this->io->ask('Overwrite schema file? (y, n)', 'n');
        }
        if ($overwrite === 'y') {
            $this->saveSchemaFile($schema, $this->settings);
        }
        $this->output->writeln('');
        $this->output->writeln('Generate migration finished');

        return 0;
    }

    /**
     * Get old database schema.
     *
     * @param array $settings
     *
     * @return mixed
     */
    private function getOldSchema(array $settings)
    {
        return $this->getSchemaFileData($settings);
    }

    /**
     * Get schema data.
     *
     * @param array $settings
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    private function getSchemaFileData(array $settings): array
    {
        $schemaFile = $this->getSchemaFilename($settings);
        $fileExt = pathinfo($schemaFile, PATHINFO_EXTENSION);

        if (!file_exists($schemaFile)) {
            return [];
        }

        if ($fileExt === 'php') {
            $data = $this->read($schemaFile);
        } elseif ($fileExt === 'json') {
            $content = file_get_contents($schemaFile) ?: '';
            $data = json_decode($content, true);
        } else {
            throw new InvalidArgumentException(sprintf('Invalid schema file extension: %s', $fileExt));
        }

        return $data;
    }

    /**
     * Generate schema filename.
     *
     * @param array $settings
     *
     * @return string Schema filename
     */
    private function getSchemaFilename(array $settings): string
    {
        // Default
        $schemaFile = sprintf('%s/%s', getcwd(), 'schema.php');
        if (!empty($settings['schema_file'])) {
            $schemaFile = $settings['schema_file'];
        }

        return $schemaFile;
    }

    /**
     * Read php file.
     *
     * @param string $filename
     *
     * @return mixed
     */
    private function read(string $filename)
    {
        return require $filename;
    }

    /**
     * Compare database schemas.
     *
     * @param array $newSchema
     * @param array $oldSchema
     *
     * @return array Difference
     */
    private function compareSchema(array $newSchema, array $oldSchema): array
    {
        $this->output->writeln('Comparing schema file to the database.');

        $arrayUtil = new ArrayUtil();

        // To add or modify
        $result = $arrayUtil->diff($newSchema, $oldSchema);

        // To remove
        $result2 = $arrayUtil->diff($oldSchema, $newSchema);

        return [$result, $result2];
    }

    /**
     * Create a class name.
     *
     * @param string $name Name
     *
     * @return string Class name
     */
    private function createClassName(string $name): string
    {
        $result = str_replace('_', ' ', $name);

        return str_replace(' ', '', ucwords($result));
    }

    /**
     * Save migration file.
     *
     * @param string $filePath Name of migration file
     * @param string $migration Migration code
     */
    private function saveMigrationFile(string $filePath, string $migration): void
    {
        $this->output->writeln(sprintf('Generate migration file: %s', $filePath));
        file_put_contents($filePath, $migration);
    }

    /**
     * Mark migration as completed.
     *
     * @param string $migrationName migrationName
     * @param string $fileName fileName
     */
    private function markMigration(string $migrationName, string $fileName): void
    {
        $this->output->writeln('Mark migration');

        $schemaTableName = $this->settings['default_migration_table'];

        /** @var AdapterInterface $adapter */
        $adapter = $this->settings['adapter'];

        // Get version from filename prefix
        $version = explode('_', $fileName)[0];

        // Record it in the database
        $time = time();
        $startTime = date('Y-m-d H:i:s', $time);
        $endTime = date('Y-m-d H:i:s', $time);
        $breakpoint = 0;

        $sql = sprintf(
            "INSERT INTO %s (%s, %s, %s, %s, %s) VALUES ('%s', '%s', '%s', '%s', %s);",
            $schemaTableName,
            $adapter->quoteColumnName('version'),
            $adapter->quoteColumnName('migration_name'),
            $adapter->quoteColumnName('start_time'),
            $adapter->quoteColumnName('end_time'),
            $adapter->quoteColumnName('breakpoint'),
            $version,
            substr($migrationName, 0, 100),
            $startTime,
            $endTime,
            $breakpoint
        );

        $adapter->query($sql);
    }

    /**
     * Save schema file.
     *
     * @param array $schema
     * @param array $settings
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    private function saveSchemaFile(array $schema, array $settings): void
    {
        $schemaFile = $this->getSchemaFilename($settings);
        $this->output->writeln(sprintf('Save schema file: %s', basename($schemaFile)));
        $fileExt = pathinfo($schemaFile, PATHINFO_EXTENSION);

        if ($fileExt === 'php') {
            $content = var_export($schema, true);
            $content = "<?php\n\nreturn " . $content . ';';
        } elseif ($fileExt === 'json') {
            $content = json_encode($schema, JSON_PRETTY_PRINT);
        } else {
            throw new InvalidArgumentException(sprintf('Invalid schema file extension: %s', $fileExt));
        }

        file_put_contents($schemaFile, $content);
    }

    /**
     * Get migration name from prompt or a generated migration name.
     *
     * @return string The migration name
     */
    private function getMigrationName(): string
    {
        if (!empty($this->settings['name'])) {
            return (string)$this->settings['name'];
        }

        $name = $this->generateDefaultMigrationName();

        if ($this->settings['generate_migration_name'] === false) {
            $name = $this->io->ask('Enter migration name', $name);
        }

        return (string)$name;
    }
}
