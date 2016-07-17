<?php

namespace Odan\Migration\Generator;

use Exception;
use PDO;
use Odan\Migration\Adapter\Database\MySqlAdapter;
use Odan\Migration\Adapter\Generator\PhinxMySqlGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * MigrationGenerator
 */
class MigrationGenerator
{

    protected $settings = array();

    /**
     * Database adapter
     *
     * @var MySqlAdapter
     */
    protected $dba;

    /**
     * Generator
     *
     * @var PhinxMySqlGenerator
     */
    protected $generator;

    /**
     * PDO
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * Database name
     *
     * @var string
     */
    protected $dbName;

    /**
     * Console output
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * Console input
     *
     * @var InputInterface
     */
    protected $input;

    /**
     * Console style
     *
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * Constructor.
     *
     * @param array $settings
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function __construct(array $settings, InputInterface $input, OutputInterface $output)
    {
        $this->settings = $settings;
        $this->pdo = $this->getPdo($settings);
        $this->dba = new MySqlAdapter($this->pdo, $output);
        $this->generator = new PhinxMySqlGenerator($this->dba, $output);
        $this->output = $output;
        $this->input = $input;
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * Generate
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool Description
     */
    public function generate()
    {
        $schema = $this->dba->getSchema();
        $oldSchema = $this->getOldSchema($this->settings);
        $diffs = $this->compareSchema($schema, $oldSchema);

        if (empty($diffs[0]) && empty($diffs[1])) {
            $this->output->writeln('No database changes detected.');
            return false;
        }

        //$name = 'Test';
        $name = $this->io->ask('Enter migration name', 'Test');
        if (empty($name)) {
            $this->output->writeln('Aborted');
            return false;
        }
        $name = $this->createName($name);
        $migration = $this->generator->createMigration($name, $diffs);
        $this->saveMigrationFile($name, $migration);

        // Overwrite schema file
        $overwrite = 'y';
        // http://symfony.com/blog/new-in-symfony-2-8-console-style-guide
        //$overwrite = $this->io->ask('Overwrite schema file? (y, n)', 'n');
        if ($overwrite == 'y') {
            $this->saveSchemaFile($schema, $this->settings);
        }

        $this->output->writeln('Generate migration finished');
        return true;
    }

    /**
     * Save migration file.
     *
     * @param string $name Name of migration
     * @param string $migration Migration code
     */
    protected function saveMigrationFile($name, $migration)
    {
        $migrationPath = $this->settings['migration_path'];
        $migrationFile = sprintf('%s/%s_%s.php', $migrationPath, date('YmdHis'), $name);
        $this->output->writeln(sprintf('Generate migration file: %s', $migrationFile));
        file_put_contents($migrationFile, $migration);
    }

    /**
     * Get old database schema.
     *
     * @param array $settings
     * @return mixed
     */
    public function getOldSchema($settings)
    {
        return $this->getSchemaFileData($settings);
    }

    /**
     * Compare database schemas.
     *
     * @param array $newSchema
     * @param array $oldSchema
     * @return array Difference
     */
    public function compareSchema($newSchema, $oldSchema)
    {
        $this->output->writeln('Comparing schema file to the database.');

        // To add or modify
        $result = $this->diff($newSchema, $oldSchema);

        // To remove
        $result2 = $this->diff($oldSchema, $newSchema);

        return array($result, $result2);
    }

    /**
     * Intersect of recursive arrays.
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    protected function diff($array1, $array2)
    {
        $difference = array();
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key]) || !is_array($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = $this->diff($value, $array2[$key]);
                    if (!empty($new_diff)) {
                        $difference[$key] = $new_diff;
                    }
                }
            } else {
                if (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
                    $difference[$key] = $value;
                }
            }
        }

        return $difference;
    }

    /**
     * Generate schema filename.
     *
     * @param array $settings
     * @return string Schema filename
     */
    public function getSchemaFilename($settings)
    {
        // Default
        $schemaFile = sprintf('%s/%s', getcwd(), 'schema.php');
        if (!empty($settings['schema_file'])) {
            $schemaFile = $settings['schema_file'];
        }
        return $schemaFile;
    }

    /**
     * Get schema data.
     *
     * @param array $settings
     * @return array
     * @throws Exception
     */
    public function getSchemaFileData($settings)
    {
        $schemaFile = $this->getSchemaFilename($settings);
        $fileExt = pathinfo($schemaFile, PATHINFO_EXTENSION);

        if (!file_exists($schemaFile)) {
            return array();
        }

        if ($fileExt == 'php') {
            $data = $this->read($schemaFile);
        } elseif ($fileExt == 'json') {
            $content = file_put_contents($schemaFile);
            $data = json_decode($content, true);
        } else {
            throw new Exception(sprintf('Invalid schema file extension: %s', $fileExt));
        }
        return $data;
    }

    /**
     * Save schema file.
     *
     * @param array $schema
     * @param array $settings
     * @throws Exception
     */
    protected function saveSchemaFile($schema, $settings)
    {
        $schemaFile = $this->getSchemaFilename($settings);
        $this->output->writeln(sprintf('Save schema file: %s', basename($schemaFile)));
        $fileExt = pathinfo($schemaFile, PATHINFO_EXTENSION);

        if ($fileExt == 'php') {
            $content = var_export($schema, true);
            $content = "<?php\n\nreturn " . $content . ';';
        } elseif ($fileExt == 'json') {
            $content = json_encode($schema, JSON_PRETTY_PRINT);
        } else {
            throw new Exception(sprintf('Invalid schema file extension: %s', $fileExt));
        }
        file_put_contents($schemaFile, $content);
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
     * Get settings from config file.
     *
     * @return array
     * @throws Exception If file not exists.
     */
    public function getSettings()
    {
        //$this->configFile = sprintf('%s/%s', getcwd(), 'migrations-config.php');
        if (!file_exists($this->configFile)) {
            throw new Exception(sprintf('File not found: %s', $this->configFile));
        }
        return $this->read($this->configFile);
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
     * Create a class name.
     *
     * @param string $name
     * @return string
     */
    protected function createName($name)
    {
        return str_replace(' ', '', ucwords($name));
    }
}
