<?php

namespace Odan\Migration\Adapter\Generator;

use Odan\Migration\Adapter\Database\SchemaAdapterInterface;
use Odan\Migration\Utility\ArrayUtil;

/**
 * PhinxMySqlGenerator.
 */
final class PhinxMySqlGenerator
{
    /**
     * Database adapter.
     *
     * @var SchemaAdapterInterface
     */
    private $dba;

    /**
     * @var ArrayUtil
     */
    private $array;

    /**
     * @var PhinxMySqlForeignKeyGenerator
     */
    private $foreignKeyCreator;

    /**
     * @var PhinxMySqlColumnGenerator
     */
    private $columnGenerator;

    /**
     * @var PhinxMySqlTableOptionGenerator
     */
    private $tableOptionGenerator;

    /**
     * @var PhinxMySqlIndexGenerator
     */
    private $indexOptionGenerator;

    /**
     * Options.
     *
     * @var array
     */
    private $options;

    /**
     * PSR-2: All PHP files MUST use the Unix LF (linefeed) line ending.
     *
     * @var string
     */
    private $nl = "\n";

    /**
     * @var string
     */
    private $ind = '    ';

    /**
     * @var string
     */
    private $ind2 = '        ';

    /**
     * @var string
     */
    private $ind3 = '            ';

    /**
     * The constructor.
     *
     * @param SchemaAdapterInterface $dba The schema adapter
     * @param array $options The options
     */
    public function __construct(SchemaAdapterInterface $dba, array $options = [])
    {
        $this->dba = $dba;
        $this->array = new ArrayUtil();
        $this->tableOptionGenerator = new PhinxMySqlTableOptionGenerator();
        $this->columnGenerator = new PhinxMySqlColumnGenerator();
        $this->indexOptionGenerator = new PhinxMySqlIndexGenerator();
        $this->foreignKeyCreator = new PhinxMySqlForeignKeyGenerator();

        $default = [
            // Experimental foreign key support.
            'foreign_keys' => false,
            // Default migration table name
            'default_migration_table' => 'phinxlog',
        ];

        $this->options = array_replace_recursive($default, $options) ?: [];
    }

    /**
     * Create migration.
     *
     * @param string $name Name of the migration
     * @param array $newSchema The new schema
     * @param array $oldSchema The old schema
     *
     * @return string The PHP code
     */
    public function createMigration(string $name, array $newSchema, array $oldSchema): string
    {
        $className = $this->options['migration_base_class'] ?? '\Phinx\Migration\AbstractMigration';

        $output = [];
        $output[] = '<?php';

        if (!empty($this->options['namespace'])) {
            $output[] = '';
            $output[] = sprintf('namespace %s;', $this->options['namespace']);
        }

        $output[] = '';
        $output[] = 'use Phinx\Db\Adapter\MysqlAdapter;';
        $output[] = '';
        $output[] = sprintf('class %s extends %s', $name, $className);
        $output[] = '{';
        $output = $this->addChangeMethod($output, $newSchema, $oldSchema);
        $output[] = '}';
        $output[] = '';

        return implode($this->nl, $output);
    }

    /**
     * Generate code for change function.
     *
     * @param string[] $output Output
     * @param array $new New schema
     * @param array $old Old schema
     *
     * @return string[] Output
     */
    private function addChangeMethod(array $output, array $new, array $old): array
    {
        $output[] = $this->ind . 'public function change()';
        $output[] = $this->ind . '{';

        if (!empty($this->options['foreign_keys'])) {
            $output[] = $this->getSetIntegrityChecks(0);
        }

        $output = $this->getTableMigrationNewDatabase($output, $new, $old);
        $output = $this->getTableMigrationTables($output, $new, $old);

        if (!empty($this->options['foreign_keys'])) {
            $output[] = $this->getSetIntegrityChecks(1);
        }

        $output[] = $this->ind . '}';

        return $output;
    }

    /**
     * Enable or disable the unique and foreign key checks.
     *
     * @param int $value The value (0 or 1)
     *
     * @return string The code
     */
    private function getSetIntegrityChecks(int $value): string
    {
        return sprintf(
            '%s$this->execute(\'SET unique_checks=%s; SET foreign_key_checks=%s;\');',
            $this->ind2,
            $value,
            $value
        );
    }

    /**
     * Get table migration (new database).
     *
     * @param array $output The outout
     * @param array $new The new schema
     * @param array $old The old schema
     *
     * @return array The new outout
     */
    private function getTableMigrationNewDatabase(array $output, array $new, array $old): array
    {
        if (empty($new['database'])) {
            return $output;
        }
        if ($this->array->neq($new, $old, ['database', 'default_character_set_name'])) {
            $output[] = $this->getAlterDatabaseCharset($new['database']['default_character_set_name']);
        }
        if ($this->array->neq($new, $old, ['database', 'default_collation_name'])) {
            $output[] = $this->getAlterDatabaseCollate($new['database']['default_collation_name']);
        }

        return $output;
    }

    /**
     * Generate alter database charset.
     *
     * @param string $charset The charset
     * @param string|null $database The database name
     *
     * @return string The sql
     */
    private function getAlterDatabaseCharset(string $charset, string $database = null): string
    {
        if ($database !== null) {
            $database = ' ' . $this->dba->ident($database);
        }
        $charset = $this->dba->quote($charset);

        return sprintf('%s$this->execute("ALTER DATABASE%s CHARACTER SET %s;");', $this->ind2, $database, $charset);
    }

    /**
     * Generate alter database collate.
     *
     * @param string $collate The collate
     * @param string|null $database The database name
     *
     * @return string The sql
     */
    private function getAlterDatabaseCollate(string $collate, string $database = null): string
    {
        if ($database) {
            $database = ' ' . $this->dba->ident($database);
        }
        $collate = $this->dba->quote($collate);

        return sprintf('%s$this->execute("ALTER DATABASE%s COLLATE=%s;");', $this->ind2, $database, $collate);
    }

    /**
     * Get table migration (new tables).
     *
     * @param array $output The outout
     * @param array $new The new schema
     * @param array $old The old schema
     *
     * @return array The new code
     */
    private function getTableMigrationTables(array $output, array $new, array $old): array
    {
        foreach ($new['tables'] ?? [] as $tableName => $table) {
            if ($tableName === $this->options['default_migration_table']) {
                continue;
            }

            $tableDiffs = $this->array->diff($new['tables'][$tableName] ?? [], $old['tables'][$tableName] ?? []);
            $tableDiffsRemove = $this->array->diff($old['tables'][$tableName] ?? [], $new['tables'][$tableName] ?? []);

            if ($tableDiffs || $tableDiffsRemove) {
                $output = $this->createTableMigrationDiff(
                    $output,
                    $new,
                    $old,
                    $table,
                    $tableName
                );
            }
        }

        // To remove
        return $this->getTableMigrationDropTables($output, $new, $old);
    }

    /**
     * Create diff commands.
     *
     * @param array $output The output
     * @param array $new The new schema
     * @param array $old The old schema
     * @param array $table The table
     * @param string $tableName The table name
     *
     * @return array The new output
     */
    private function createTableMigrationDiff(
        array $output,
        array $new,
        array $old,
        array $table,
        string $tableName
    ): array {
        $output[] = $this->getTableVariable($table, $tableName);

        // To add or modify
        $output = $this->columnGenerator->getTableMigrationNewTablesColumns(
            $output,
            $table,
            $tableName,
            $new,
            $old
        );

        $output = $this->columnGenerator->getTableMigrationOldTablesColumns($output, $tableName, $new, $old);

        $output = $this->indexOptionGenerator->getTableMigrationIndexes(
            $output,
            $table,
            $tableName,
            $new,
            $old
        );

        if (!empty($this->options['foreign_keys'])) {
            $output = $this->foreignKeyCreator->getForeignKeysMigrations($output, $tableName, $new, $old);
        }

        if (isset($old['tables'][$tableName])) {
            // Update existing table
            $output[] = sprintf('%s->save();', $this->ind3);
        } else {
            // Create new table
            $output[] = sprintf('%s->create();', $this->ind3);
        }

        return $output;
    }

    /**
     * Generate create table variable.
     *
     * @param array $table The table
     * @param string $tableName The table name
     *
     * @return string The code
     */
    private function getTableVariable(array $table, string $tableName): string
    {
        $tableOptions = $this->tableOptionGenerator->getTableOptions($table);

        return sprintf('%s$this->table(\'%s\', %s)', $this->ind2, $tableName, $tableOptions);
    }

    /**
     * Get table migration (old tables).
     *
     * @param array $output
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    private function getTableMigrationDropTables(array $output, array $new, array $old): array
    {
        if (empty($old['tables'])) {
            return $output;
        }

        foreach ($old['tables'] as $tableName => $table) {
            if ($tableName === $this->options['default_migration_table']) {
                continue;
            }

            if (!isset($new['tables'][$tableName])) {
                $output[] = $this->getDropTable($tableName);
            }
        }

        return $output;
    }

    /**
     * Generate drop table.
     *
     * @param string $table The table
     *
     * @return string The code
     */
    private function getDropTable(string $table): string
    {
        return sprintf('%s$this->table(\'%s\')->drop()->save();', $this->ind2, $table);
    }
}
