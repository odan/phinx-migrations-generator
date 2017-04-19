<?php

namespace Odan\Migration\Generator;

use Exception;
use PDO;
use Odan\Migration\Adapter\Database\MySqlAdapter;
use Odan\Migration\Adapter\Generator\PhinxMySqlGenerator;
use Phinx\Migration\Manager;
use Phinx\Migration\Manager\Environment;
use Phinx\Util\Util;
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
     * @return int Status
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

        if (empty($this->settings['name'])) {
            $name = $this->io->ask('Enter migration name', '');
        } else {
            $name = $this->settings['name'];
        }
        if (empty($name)) {
            $this->output->writeln('Aborted');
            return false;
        }
        $path = $this->settings['migration_path'];
        $className = $this->createClassName($name);

        if (!Util::isValidPhinxClassName($className)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" is invalid. Please use CamelCase format.',
                $className
            ));
        }

        if (!Util::isUniqueMigrationClassName($className, $path)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" already exists',
                $className
            ));
        }

        // Compute the file path
        $fileName = Util::mapClassNameToFileName($className);
        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $filePath
            ));
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
        if ($overwrite == 'y') {
            $this->saveSchemaFile($schema, $this->settings);
        }
        $this->output->writeln('');
        $this->output->writeln('Generate migration finished');

        return 0;
    }

    /**
     * Mark migration as completed.
     *
     * @param string $migrationName migrationName
     * @param string $fileName fileName
     */
    protected function markMigration($migrationName, $fileName)
    {
        $this->output->writeln('Mark migration');

        /* @var $adapter \Phinx\Db\Adapter\AdapterInterface */
        $adapter = $this->settings['adapter'];

        /* @var $manager Manager */
        $manager =  $this->settings['manager'];

        $envName = $this->settings['environment'];

        /* @var $env Environment */
        $env = $manager->getEnvironment($envName);
        $schemaTableName = $env->getSchemaTableName();

        /* @var $pdo \PDO */
        $pdo = $this->settings['adapter'];

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

        $pdo->query($sql);
    }

    /**
     * Save migration file.
     *
     * @param string $filePath Name of migration file
     * @param string $migration Migration code
     */
    protected function saveMigrationFile($filePath, $migration)
    {
        $this->output->writeln(sprintf('Generate migration file: %s', $filePath));
        file_put_contents($filePath, $migration);
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
            $content = file_get_contents($schemaFile);
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
        if (isset($settings['pdo']) && $settings['pdo'] instanceof PDO) {
            $settings['pdo']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $settings['pdo']->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $settings['pdo'];
        }
        $options = array_replace_recursive($settings['options'], [
            // Enable exceptions
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Set default fetch mode
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo = new PDO($settings['dsn'], $settings['username'], $settings['password'], $options);
        return $pdo;
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
     * @param string $name Name
     * @return string Class name
     */
    protected function createClassName($name)
    {
        $result = str_replace('_', ' ', $name);
        return str_replace(' ', '', ucwords($result));
    }
}
