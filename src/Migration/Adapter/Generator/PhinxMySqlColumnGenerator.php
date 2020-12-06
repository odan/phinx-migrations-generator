<?php

namespace Odan\Migration\Adapter\Generator;

use Odan\Migration\Adapter\Database\SchemaAdapterInterface;
use Odan\Migration\Utility\ArrayUtil;

/**
 * Generator.
 */
final class PhinxMySqlColumnGenerator
{
    /**
     * @var ArrayUtil
     */
    private $array;

    /**
     * @var PhinxMySqlColumnOptionGenerator
     */
    private $columnOptionGenerator;

    /**
     * @var string
     */
    private $ind3 = '            ';

    /**
     * The constructor.
     *
     * @param SchemaAdapterInterface $dba
     */
    public function __construct(SchemaAdapterInterface $dba)
    {
        $this->array = new ArrayUtil();
        $this->columnOptionGenerator = new PhinxMySqlColumnOptionGenerator($dba);
    }

    /**
     * Get table migration (new table columns).
     *
     * @param array $output
     * @param array $table
     * @param string $tableName
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    public function getTableMigrationNewTablesColumns(
        array $output,
        array $table,
        string $tableName,
        array $new,
        array $old
    ): array {
        if (empty($table['columns'])) {
            return $output;
        }

        // Remove not used keys
        $this->array->unsetArrayKeys($new, 'COLUMN_KEY');
        $this->array->unsetArrayKeys($old, 'COLUMN_KEY');

        foreach ($table['columns'] as $columnName => $columnData) {
            if (!isset($old['tables'][$tableName]['columns'][$columnName])) {
                $output[] = $this->getColumnCreateAddNoUpdate($new, $tableName, $columnName);
            } elseif ($this->array->neq($new, $old, ['tables', $tableName, 'columns', $columnName])) {
                $output[] = $this->getColumnUpdate($new, $tableName, $columnName);
            }
        }

        return $output;
    }

    /**
     * Get addColumn method.
     *
     * @param array $schema
     * @param string $table
     * @param string $columnName
     *
     * @return string
     */
    private function getColumnCreateAddNoUpdate(array $schema, string $table, string $columnName): string
    {
        $result = $this->getColumnCreate($schema, $table, $columnName);

        return sprintf("%s->addColumn('%s', '%s', %s)", $this->ind3, $result[1], $result[2], $result[3]);
    }

    /**
     * Generate column update.
     *
     * @param array $schema
     * @param string $table
     * @param string $columnName
     *
     * @return string
     */
    private function getColumnUpdate(array $schema, string $table, string $columnName): string
    {
        $columns = $schema['tables'][$table]['columns'];
        $columnData = $columns[$columnName];

        $phinxType = $this->getPhinxColumnType($columnData);
        $columnAttributes = $this->columnOptionGenerator->getPhinxColumnOptions($phinxType, $columnData, $columns);

        return sprintf("%s->changeColumn('%s', '%s', %s)", $this->ind3, $columnName, $phinxType, $columnAttributes);
    }

    /**
     * Generate column create.
     *
     * @param array $schema The schema
     * @param string $tableName The table name
     * @param string $columnName The column name
     *
     * @return string[] The table specification
     */
    private function getColumnCreate(array $schema, string $tableName, string $columnName): array
    {
        $columns = $schema['tables'][$tableName]['columns'];
        $columnData = $columns[$columnName];
        $phinxType = $this->getPhinxColumnType($columnData);
        $columnAttributes = $this->columnOptionGenerator->getPhinxColumnOptions($phinxType, $columnData, $columns);

        return [$tableName, $columnName, $phinxType, $columnAttributes];
    }

    /**
     * Map MySql data type to Phinx\Db\Adapter\AdapterInterface::PHINX_TYPE_*.
     *
     * @param array $columnData The column type
     *
     * @return string The type
     */
    private function getPhinxColumnType(array $columnData): string
    {
        $columnType = $columnData['COLUMN_TYPE'];
        if ($columnType === 'tinyint(1)') {
            return 'boolean';
        }
        $map = [
            'tinyint' => 'integer',
            'smallint' => 'integer',
            'int' => 'integer',
            'mediumint' => 'integer',
            'bigint' => 'biginteger',
            'tinytext' => 'text',
            'mediumtext' => 'text',
            'longtext' => 'text',
            'varchar' => 'string',
            'tinyblob' => 'blob',
            'mediumblob' => 'blob',
            'longblob' => 'blob',
        ];

        $type = $this->columnOptionGenerator->getMySQLColumnType($columnData);

        return $map[$type] ?? $type;
    }

    /**
     * Get table migration (old table columns).
     *
     * @param array $output
     * @param string $tableName
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    public function getTableMigrationOldTablesColumns(array $output, string $tableName, array $new, array $old): array
    {
        if (empty($old['tables'][$tableName]['columns'])) {
            return $output;
        }

        foreach ($old['tables'][$tableName]['columns'] as $oldColumnName => $oldColumnData) {
            if (!isset($new['tables'][$tableName]['columns'][$oldColumnName])) {
                $output = $this->getColumnRemove($output, $oldColumnName);
            }
        }

        return $output;
    }

    /**
     * Generate column remove.
     *
     * @param array $output
     * @param string $columnName
     *
     * @return array
     */
    private function getColumnRemove(array $output, string $columnName): array
    {
        $output[] = sprintf("%s->removeColumn('%s')", $this->ind3, $columnName);

        return $output;
    }
}
