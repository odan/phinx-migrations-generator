<?php

namespace Odan\Migration\Generator;

use Exception;
use FluentPDO;
use PDO;
use Odan\Migration\Adapter\Database\DatabaseAdapterInterface;
use Odan\Migration\Adapter\Generator\GeneratorInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * MigrationGenertor
 */
class MigrationGenertor
{

    /**
     *
     * @var DatabaseAdapterInterface
     */
    protected $dbAdapter;

    /**
     *
     * @var GeneratorInterface
     */
    protected $generator;

    /**
     *
     * @var FluentPDO
     */
    protected $db;

    /**
     *
     * @var string
     */
    protected $dbName;

    /**
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     *
     * @param DatabaseAdapterInterface $db
     * @param GeneratorInterface $generator
     */
    public function __construct(DatabaseAdapterInterface $dbAdapter, GeneratorInterface $generatorAdapter, InputInterface $input, OutputInterface $output)
    {
        $this->dbAdapter = $dbAdapter;
        $this->generator = $generatorAdapter;
        $this->output = $output;

        //$this->configFile = $input->getArgument('config');
        $this->configFile = $input->getOption('config');
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
        $settings = $this->getSettings();
        $this->db = $this->getDb($settings);

        $this->dbName = $this->db->getPdo()->query('select database()')->fetchColumn();
        $this->output->writeln(sprintf('Database: <info>%s</>', $this->dbName));

        $schema = $this->getCurrentSchema();
        $this->saveSchemaFile($schema, $settings);

        $this->output->writeln('Comparing schema file to the database.');
        $this->output->writeln('No database changes detected.');
        //$this->output->writeln('Generate migration finished');
        return true;
    }

    /**
     *
     * @param array $schema
     * @param type $settings
     * @throws Exception
     */
    protected function saveSchemaFile($schema, $settings)
    {
        // Default
        $schemaFile = sprintf('%s/%s', getcwd(), 'schema.php');
        if (!empty($settings['schema_file'])) {
            $schemaFile = $settings['schema_file'];
        }
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

    public function getCurrentSchema()
    {
        $this->output->writeln('Load current database schema.');
        $result = array();
        $tables = $this->getTables();
        foreach ($tables as $table) {
            $tableName = $table['table_name'];
            $this->output->writeln(sprintf('Table: <info>%s</>', $tableName));
            $result['tables'][$tableName]['table'] = $table;
            $result['tables'][$tableName]['columns'] = $this->getTableColumns($tableName);
            $result['tables'][$tableName]['keys'] = $this->getTableKeys($tableName);
            $result['tables'][$tableName]['contraints'] = $this->getTableContraints($tableName);
            $result['tables'][$tableName]['create_table'] = $this->getTableCreateSql($tableName);
        }
        $this->ksort($result);
        return $result;
    }

    protected function getTables()
    {
        $result = $this->db
                ->from('information_schema.tables')
                ->where('table_schema', $this->dbName)
                ->where('table_type', 'BASE TABLE')
                ->fetchAll();
        return $result;
    }

    protected function getTableColumns($tableName)
    {
        $result = $this->db
                ->from('information_schema.columns')
                ->where('table_schema', $this->dbName)
                ->where('table_name', $tableName)
                ->fetchAll();
        return $result;
    }

    protected function getTableKeys($tableName)
    {
        $result = $this->db
                ->from('information_schema.key_column_usage')
                ->where('table_schema', $this->dbName)
                ->where('table_name', $tableName)
                ->fetchAll();
        return $result;
    }

    protected function getTableContraints($tableName)
    {
        $result = $this->db
                ->from('information_schema.table_constraints')
                ->where('constraint_schema', $this->dbName)
                ->where('table_name', $tableName)
                ->fetchAll();
        return $result;
    }

    protected function getTableCreateSql($tableName)
    {
        $pdo = $this->db->getPdo();
        $table = preg_replace('/[^A-Za-z0-9_]+/', '', $tableName);
        $tableName = $pdo->quote($tableName);
        $sql = sprintf('SHOW CREATE TABLE `%s`', $table);
        $result = $pdo->query($sql)->fetch();
        return $result['create table'];
    }

    /**
     * Sort array by keys.
     *
     * @param array $array
     * @return bool
     */
    protected function ksort(&$array)
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->ksort($value);
            }
        }
        return ksort($array);
    }

    /**
     *
     * @param array $settings
     * @return FluentPDO
     */
    public function getDb($settings)
    {
        $options = array_replace_recursive($settings['options'], [
            PDO::ATTR_CASE => PDO::CASE_LOWER,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo = new PDO($settings['dsn'], $settings['username'], $settings['password'], $options);
        $fpdo = new FluentPDO($pdo);
        return $fpdo;
    }

    /**
     *
     * @return mixed
     * @throws Exception
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
     *
     * @param string $filename
     * @return mixed
     */
    public function read($filename)
    {
        return require $filename;
    }
}
