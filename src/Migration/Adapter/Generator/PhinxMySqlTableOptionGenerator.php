<?php

namespace Odan\Migration\Adapter\Generator;

use Odan\Migration\Utility\ArrayUtil;

/**
 * Generator.
 */
final class PhinxMySqlTableOptionGenerator
{
    /**
     * @var ArrayUtil
     */
    private $array;

    /**
     * The constructor.
     */
    public function __construct()
    {
        $this->array = new ArrayUtil();
    }

    /**
     * Get table options.
     *
     * @param array $table The table
     *
     * @return string The code
     */
    public function getTableOptions(array $table): string
    {
        $attributes = [];

        $attributes = $this->getPhinxTablePrimaryKey($attributes, $table);

        // collation
        $attributes = $this->getPhinxTableEngine($attributes, $table);

        // encoding
        $attributes = $this->getPhinxTableEncoding($attributes, $table);

        // collation
        $attributes = $this->getPhinxTableCollation($attributes, $table);

        // comment
        $attributes = $this->getPhinxTableComment($attributes, $table);

        // row_format
        $attributes = $this->getPhinxTableRowFormat($attributes, $table);

        return $this->array->prettifyArray($attributes, 3);
    }

    /**
     * Define table id value.
     *
     * @param array $attributes The attributes
     * @param array $table The table
     *
     * @return array The new attributes
     */
    private function getPhinxTablePrimaryKey(array $attributes, array $table): array
    {
        $primaryKeys = $this->getPrimaryKeys($table);
        $attributes['id'] = false;

        if (!empty($primaryKeys)) {
            $attributes['primary_key'] = $primaryKeys;
        }

        return $attributes;
    }

    /**
     * Collect alternate primary keys.
     *
     * @param array $table The table
     *
     * @return array The keys
     */
    private function getPrimaryKeys(array $table): array
    {
        $primaryKeys = [];
        foreach ($table['columns'] as $column) {
            $columnName = $column['COLUMN_NAME'];
            $columnKey = $column['COLUMN_KEY'];
            if ($columnKey !== 'PRI') {
                continue;
            }
            $primaryKeys[] = $columnName;
        }

        return $primaryKeys;
    }

    /**
     * Define table engine (defaults to InnoDB).
     *
     * @param array $attributes The attributes
     * @param array $table The table
     *
     * @return array The attributes
     */
    private function getPhinxTableEngine(array $attributes, array $table): array
    {
        if (!empty($table['table']['engine'])) {
            $attributes['engine'] = $table['table']['engine'];
        } else {
            $attributes['engine'] = 'InnoDB';
        }

        return $attributes;
    }

    /**
     * Define table character set (defaults to utf8).
     *
     * @param array $attributes The attributes
     * @param array $table The table
     *
     * @return array The attributes
     */
    private function getPhinxTableEncoding(array $attributes, array $table): array
    {
        if (!empty($table['table']['character_set_name'])) {
            $attributes['encoding'] = $table['table']['character_set_name'];
        } else {
            $attributes['encoding'] = 'utf8';
        }

        return $attributes;
    }

    /**
     * Define table collation (defaults to `utf8_general_ci`).
     *
     * @param array $attributes The attributes
     * @param array $table The table
     *
     * @return array The attributes
     */
    private function getPhinxTableCollation(array $attributes, array $table): array
    {
        if (!empty($table['table']['table_collation'])) {
            $attributes['collation'] = $table['table']['table_collation'];
        } else {
            $attributes['collation'] = 'utf8_general_ci';
        }

        return $attributes;
    }

    /**
     * Set a text comment on the table.
     *
     * @param array $attributes The attributes
     * @param array $table The table
     *
     * @return array The attributes
     */
    private function getPhinxTableComment(array $attributes, array $table): array
    {
        if (!empty($table['table']['table_comment'])) {
            $attributes['comment'] = $table['table']['table_comment'];
        } else {
            $attributes['comment'] = '';
        }

        return $attributes;
    }

    /**
     * Get table for format.
     *
     * @param array $attributes The attributes
     * @param array $table The table
     *
     * @return array The attributes
     */
    private function getPhinxTableRowFormat(array $attributes, array $table): array
    {
        if (!empty($table['table']['row_format'])) {
            $attributes['row_format'] = strtoupper($table['table']['row_format']);
        }

        return $attributes;
    }
}
