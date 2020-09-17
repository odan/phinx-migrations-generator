<?php

namespace Odan\Migration\Adapter\Generator;

use Odan\Migration\Utility\ArrayUtil;

/**
 * Generator.
 */
final class PhinxMySqlIndexGenerator
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
     * Get table migration (indexes).
     *
     * @param array $output
     * @param array $table
     * @param string $tableName
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    public function getTableMigrationIndexes(
        array $output,
        array $table,
        string $tableName,
        array $new,
        array $old
    ): array {
        if (empty($table['indexes'])) {
            return $output;
        }
        foreach ($table['indexes'] as $indexName => $indexSequences) {
            if (!isset($old['tables'][$tableName]['indexes'][$indexName])) {
                $output = $this->getIndexCreate($output, $new, $tableName, $indexName);
            } elseif ($this->array->neq($new, $old, ['tables', $tableName, 'indexes', $indexName])) {
                if ($indexName !== 'PRIMARY') {
                    $output = $this->getIndexRemove($indexName, $output);
                }
                $output = $this->getIndexCreate($output, $new, $tableName, $indexName);
            }
        }

        // To delete
        if (!empty($old['tables'][$tableName]['indexes'])) {
            foreach ($old['tables'][$tableName]['indexes'] as $indexName => $indexSequences) {
                if (!isset($new['tables'][$tableName]['indexes'][$indexName])) {
                    $output = $this->getIndexRemove($indexName, $output);
                }
            }
        }

        return $output;
    }

    /**
     * Generate index create.
     *
     * @param string[] $output Output
     * @param array $schema Schema
     * @param string $table Tablename
     * @param string $indexName Index name
     *
     * @return array Output
     */
    private function getIndexCreate(array $output, array $schema, string $table, string $indexName): array
    {
        if ($indexName === 'PRIMARY') {
            return $output;
        }
        $indexes = $schema['tables'][$table]['indexes'];
        $indexSequences = $indexes[$indexName];

        $indexFields = $this->getIndexFields($indexSequences);
        $indexOptions = $this->getIndexOptions(array_values($indexSequences)[0]);

        $output[] = sprintf('%s->addIndex(%s, %s)', $this->ind3, $indexFields, $indexOptions);

        return $output;
    }

    /**
     * Generate index remove.
     *
     * @param string $indexName
     * @param array $output
     *
     * @return array
     */
    private function getIndexRemove(string $indexName, array $output): array
    {
        $output[] = sprintf('%s->removeIndexByName("%s")', $this->ind3, $indexName);

        return $output;
    }

    /**
     * Get index fields.
     *
     * @param array $indexSequences
     *
     * @return string The code
     */
    private function getIndexFields(array $indexSequences): string
    {
        $indexFields = [];
        foreach ($indexSequences as $indexData) {
            $indexFields[] = $indexData['Column_name'];
        }

        return $this->array->prettifyArray($indexFields, 3);
    }

    /**
     * Generate index options.
     *
     * @param array $indexData
     *
     * @return string The code
     */
    private function getIndexOptions(array $indexData): string
    {
        $tableOptions = [];

        if (isset($indexData['Key_name'])) {
            $tableOptions['name'] = $indexData['Key_name'];
        }
        if (isset($indexData['Non_unique']) && (int)$indexData['Non_unique'] === 1) {
            $tableOptions['unique'] = false;
        } else {
            $tableOptions['unique'] = true;
        }

        //Number of characters for nonbinary string types (CHAR, VARCHAR, TEXT)
        // and number of bytes for binary string types (BINARY, VARBINARY, BLOB)
        if (isset($indexData['Sub_part'])) {
            $tableOptions['limit'] = $indexData['Sub_part'];
        }
        // MyISAM only
        if (isset($indexData['Index_type']) && $indexData['Index_type'] === 'FULLTEXT') {
            $tableOptions['type'] = 'fulltext';
        }

        $result = '';
        if (!empty($tableOptions)) {
            $result = $this->array->prettifyArray($tableOptions, 3);
        }

        return $result;
    }
}
