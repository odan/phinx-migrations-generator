<?php

namespace Odan\Migration\Adapter\Generator;

use Odan\Migration\Utility\ArrayUtil;

/**
 * Generator.
 */
final class PhinxMySqlForeignKeyGenerator
{
    /**
     * @var ArrayUtil
     */
    private $array;

    /**
     * @var string
     */
    private $ind3 = '            ';

    /**
     * The constructor.
     */
    public function __construct()
    {
        $this->array = new ArrayUtil();
    }

    /**
     * Generate foreign keys migrations.
     *
     * @param array $output
     * @param string $tableName
     * @param array $new New schema
     * @param array $old Old schema
     *
     * @return array Output
     */
    public function getForeignKeysMigrations(array $output, string $tableName, array $new = [], array $old = []): array
    {
        if (empty($new['tables'][$tableName])) {
            return [];
        }

        $newTable = $new['tables'][$tableName];

        $oldTable = !empty($old['tables'][$tableName]) ? $old['tables'][$tableName] : [];

        if (!empty($oldTable['foreign_keys'])) {
            foreach ($oldTable['foreign_keys'] as $fkName => $fkData) {
                if (!isset($newTable['foreign_keys'][$fkName])) {
                    $columnName = (string)$fkData['COLUMN_NAME'];
                    $output = $this->getForeignKeyRemove($output, $columnName);
                }
            }
        }

        if (!empty($newTable['foreign_keys'])) {
            foreach ($newTable['foreign_keys'] as $fkName => $fkData) {
                if (!isset($oldTable['foreign_keys'][$fkName])) {
                    $output = $this->getForeignKeyCreate($output, $fkName, $fkData);
                }
            }
        }

        return $output;
    }

    /**
     * Generate foreign key remove.
     *
     * @param array $output
     * @param string $indexName
     *
     * @return array
     */
    private function getForeignKeyRemove(array $output, string $indexName): array
    {
        $output[] = sprintf("%s->dropForeignKey('%s')", $this->ind3, $indexName);

        return $output;
    }

    /**
     * Generate foreign key create.
     *
     * @param array $output
     * @param string $fkName
     * @param array $fkData
     *
     * @return array
     */
    private function getForeignKeyCreate(array $output, string $fkName, array $fkData): array
    {
        $columns = "'" . $fkData['COLUMN_NAME'] . "'";
        $referencedTable = "'" . $fkData['REFERENCED_TABLE_NAME'] . "'";
        $referencedColumns = "'" . $fkData['REFERENCED_COLUMN_NAME'] . "'";
        $tableOptions = $this->getForeignKeyOptions($fkData, $fkName);

        $output[] = sprintf(
            '%s->addForeignKey(%s, %s, %s, %s)',
            $this->ind3,
            $columns,
            $referencedTable,
            $referencedColumns,
            $tableOptions
        );

        return $output;
    }

    /**
     * Generate foreign key options.
     *
     * @param array $fkData The foreign key data
     * @param string|null $fkName The foreign key name
     *
     * @return string The code
     */
    private function getForeignKeyOptions(array $fkData, ?string $fkName = null): string
    {
        $tableOptions = [];
        if (isset($fkName)) {
            $tableOptions['constraint'] = $fkName;
        }
        if (isset($fkData['UPDATE_RULE'])) {
            $tableOptions['update'] = $this->getForeignKeyRuleValue($fkData['UPDATE_RULE']);
        }
        if (isset($fkData['DELETE_RULE'])) {
            $tableOptions['delete'] = $this->getForeignKeyRuleValue($fkData['DELETE_RULE']);
        }

        return $this->array->prettifyArray($tableOptions, 3);
    }

    /**
     * Generate foreign key rule value.
     *
     * @param string $value
     *
     * @return string
     */
    private function getForeignKeyRuleValue(string $value): string
    {
        $mappings = [
            'no action' => 'NO_ACTION',
            'cascade' => 'CASCADE',
            'restrict' => 'RESTRICT',
            'set null' => 'SET_NULL',
        ];

        return $mappings[strtolower($value)] ?? 'NO_ACTION';
    }
}
