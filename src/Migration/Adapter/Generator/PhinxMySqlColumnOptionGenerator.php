<?php

namespace Odan\Migration\Adapter\Generator;

use Odan\Migration\Adapter\Database\SchemaAdapterInterface;
use Odan\Migration\Utility\ArrayUtil;
use Phinx\Db\Adapter\AdapterInterface;

/**
 * Generator.
 */
final class PhinxMySqlColumnOptionGenerator
{
    /**
     * @var SchemaAdapterInterface
     */
    private $dba;

    /**
     * @var ArrayUtil
     */
    private $array;

    /**
     * The constructor.
     *
     * @param SchemaAdapterInterface $dba
     */
    public function __construct(SchemaAdapterInterface $dba)
    {
        $this->dba = $dba;
        $this->array = new ArrayUtil();
    }

    /**
     * Generate phinx column options.
     *
     * https://media.readthedocs.org/pdf/phinx/latest/phinx.pdf
     *
     * @param string $phinxType The phinx type
     * @param array $columnData The column data
     * @param array $columns The columns
     *
     * @return string THe code
     */
    public function getPhinxColumnOptions(string $phinxType, array $columnData, array $columns): string
    {
        $attributes = [];

        $attributes = $this->getPhinxColumnOptionsNull($attributes, $columnData);

        // Default value
        $attributes = $this->getPhinxColumnOptionsDefault($phinxType, $attributes, $columnData);

        // For timestamp columns
        $attributes = $this->getPhinxColumnOptionsTimestamp($attributes, $columnData);

        // Limit / length
        $attributes = $this->getPhinxColumnOptionsLimit($attributes, $columnData);

        // Numeric attributes
        $attributes = $this->getPhinxColumnOptionsNumeric($attributes, $columnData);

        // Enum and set values
        if ($phinxType === AdapterInterface::PHINX_TYPE_ENUM || $phinxType === AdapterInterface::PHINX_TYPE_SET) {
            $attributes = $this->getOptionEnumAndSetValues($attributes, $columnData);
        }

        // Collation
        $attributes = $this->getPhinxColumnCollation($phinxType, $attributes, $columnData);

        // Encoding
        $attributes = $this->getPhinxColumnEncoding($phinxType, $attributes, $columnData);

        // Comment
        $attributes = $this->getPhinxColumnOptionsComment($attributes, $columnData);

        // After: specify the column that a new column should be placed after
        $attributes = $this->getPhinxColumnOptionsAfter($attributes, $columnData, $columns);

        return $this->array->prettifyArray($attributes, 3);
    }

    /**
     * Get column type.
     *
     * @param array $columnData The column data
     *
     * @return string The type
     */
    public function getMySQLColumnType(array $columnData): string
    {
        $match = null;
        preg_match('/^[a-z]+/', $columnData['COLUMN_TYPE'], $match);

        return $match[0];
    }

    /**
     * Generate phinx column options (null).
     *
     * @param array $attributes The attributes
     * @param array $columnData The column data
     *
     * @return array The attributes
     */
    private function getPhinxColumnOptionsNull(array $attributes, array $columnData): array
    {
        // has NULL
        if ($columnData['IS_NULLABLE'] === 'YES') {
            $attributes['null'] = true;
        } else {
            $attributes['null'] = false;
        }

        return $attributes;
    }

    /**
     * Generate phinx column options (default value).
     *
     * @param string $phinxType The phinx type
     * @param array $attributes The attributes
     * @param array $columnData The column data
     *
     * @return array The attributes
     */
    private function getPhinxColumnOptionsDefault(string $phinxType, array $attributes, array $columnData): array
    {
        if ($columnData['COLUMN_DEFAULT'] !== null) {
            $attributes['default'] = $columnData['COLUMN_DEFAULT'];
        }

        if (
            $phinxType === AdapterInterface::PHINX_TYPE_DATETIME
            && isset($attributes['default'])
            && strtolower($attributes['default']) === 'current_timestamp()'
        ) {
            $attributes['default'] = 'CURRENT_TIMESTAMP';

            // Return here because we do not want to escape it for MariaDB
            return $attributes;
        }

        if (isset($attributes['default']) && $phinxType === AdapterInterface::PHINX_TYPE_BIT) {
            // Note that default values like b'1111' are not supported by phinx
            $bitMappings = [
                "b'1'" => true,
                "b'0'" => false,
            ];

            $attributes['default'] = $bitMappings[$attributes['default']] ?? $attributes['default'];

            // Return here because we do not want to escape it for MariaDB
            return $attributes;
        }

        // MariaDB contains 'NULL' as string to define null as default
        if ($columnData['COLUMN_DEFAULT'] === 'NULL') {
            $attributes['default'] = null;
        }

        // MariaDB quotes the values
        if (isset($attributes['default']) && $this->isMariaDb()) {
            $attributes['default'] = trim($attributes['default'], "'");
        }

        return $attributes;
    }

    /**
     * Is MariaDB.
     *
     * @return bool True if it's a MaraDB database
     */
    private function isMariaDb(): bool
    {
        return stripos($this->dba->getVersion(), 'maria') !== false;
    }

    /**
     * Generate phinx column options (update).
     *
     * @param array $attributes The attributes
     * @param array $columnData The column data
     *
     * @return array The attributes
     */
    private function getPhinxColumnOptionsTimestamp(array $attributes, array $columnData): array
    {
        // default set default value (use with CURRENT_TIMESTAMP)
        // on update CURRENT_TIMESTAMP
        if (stripos($columnData['EXTRA'], 'on update CURRENT_TIMESTAMP') !== false) {
            $attributes['update'] = 'CURRENT_TIMESTAMP';
        }

        return $attributes;
    }

    /**
     * Generate phinx column options (update).
     *
     * @param array $attributes The attributes
     * @param array $columnData The column data
     *
     * @return array The attributes
     */
    private function getPhinxColumnOptionsLimit(array $attributes, array $columnData): array
    {
        $limit = $this->getColumnLimit($columnData);
        if ($limit !== null) {
            $attributes['limit'] = $limit;
        }

        return $attributes;
    }

    /**
     * Generate column limit.
     *
     * @param array $columnData
     *
     * @return int|RawPhpValue|null The limit
     */
    private function getColumnLimit(array $columnData): RawPhpValue|int|null
    {
        $type = $this->getMySQLColumnType($columnData);

        $mappings = [
            'int' => 'MysqlAdapter::INT_REGULAR',
            'tinyint' => 'MysqlAdapter::INT_TINY',
            'smallint' => 'MysqlAdapter::INT_SMALL',
            'mediumint' => 'MysqlAdapter::INT_MEDIUM',
            'bigint' => 'MysqlAdapter::INT_BIG',
            'tinytext' => 'MysqlAdapter::TEXT_TINY',
            'mediumtext' => 'MysqlAdapter::TEXT_MEDIUM',
            'longtext' => 'MysqlAdapter::TEXT_LONG',
            'longblob' => 'MysqlAdapter::BLOB_LONG',
            'mediumblob' => 'MysqlAdapter::BLOB_MEDIUM',
            'blob' => 'MysqlAdapter::BLOB_REGULAR',
            'tinyblob' => 'MysqlAdapter::BLOB_TINY',
        ];

        $adapterConst = $mappings[$type] ?? null;

        if ($adapterConst) {
            return new RawPhpValue($adapterConst);
        }

        if (!empty($columnData['CHARACTER_MAXIMUM_LENGTH'])) {
            return (int)$columnData['CHARACTER_MAXIMUM_LENGTH'];
        }

        if (preg_match('/\((\d+)\)/', $columnData['COLUMN_TYPE'], $match) === 1) {
            return (int)$match[1];
        }

        return null;
    }

    /**
     * Generate phinx column options (default value).
     *
     * @param array $attributes The attributes
     * @param array $columnData The column data
     *
     * @return array The attributes
     */
    private function getPhinxColumnOptionsNumeric(array $attributes, array $columnData): array
    {
        $dataType = $columnData['DATA_TYPE'];

        $intDefaultLimits = [
            'int' => '11',
            'bigint' => '20',
        ];

        // For integer and biginteger columns
        if ($dataType === 'int' || $dataType === 'bigint') {
            $match = null;
            if (preg_match('/\((\d+)\)/', $columnData['COLUMN_TYPE'], $match) === 1) {
                if ($match[1] !== $intDefaultLimits[$dataType]) {
                    $attributes['limit'] = (int)$match[1];
                }
            }

            // signed enable or disable the unsigned option (only applies to MySQL)
            $match = null;
            $pattern = '/unsigned$/';
            if (preg_match($pattern, $columnData['COLUMN_TYPE'], $match) === 1) {
                $attributes['signed'] = false;
            }

            // identity enable or disable automatic incrementing
            if ($columnData['EXTRA'] === 'auto_increment') {
                $attributes['identity'] = true;
            }
        }

        // For decimal columns
        if ($dataType === 'decimal') {
            // Set decimal accuracy
            if (!empty($columnData['NUMERIC_PRECISION'])) {
                $attributes['precision'] = $columnData['NUMERIC_PRECISION'];
            }
            if (!empty($columnData['NUMERIC_SCALE'])) {
                $attributes['scale'] = $columnData['NUMERIC_SCALE'];
            }
        }

        return $attributes;
    }

    /**
     * Generate option enum values.
     *
     * @param array $attributes The attributes
     * @param array $columnData The column data
     *
     * @return array The attributes
     */
    private function getOptionEnumAndSetValues(array $attributes, array $columnData): array
    {
        $match = null;
        $pattern = '/(enum|set)\((.*)\)/';
        if (preg_match($pattern, $columnData['COLUMN_TYPE'], $match) === 1) {
            $values = str_getcsv($match[2], ',', "'", "\\");
            $attributes['values'] = $values;
        }

        return $attributes;
    }

    /**
     * Set collation that differs from table defaults (only applies to MySQL).
     *
     * @param string $phinxType The phinx type
     * @param array $attributes The attributes
     * @param array $columnData The column data
     *
     * @return array The attributes
     */
    private function getPhinxColumnCollation(string $phinxType, array $attributes, array $columnData): array
    {
        $allowedTypes = [
            AdapterInterface::PHINX_TYPE_CHAR,
            AdapterInterface::PHINX_TYPE_STRING,
            AdapterInterface::PHINX_TYPE_TEXT,
        ];
        if (!in_array($phinxType, $allowedTypes, true)) {
            return $attributes;
        }

        if (!empty($columnData['COLLATION_NAME'])) {
            $attributes['collation'] = $columnData['COLLATION_NAME'];
        }

        return $attributes;
    }

    /**
     * Set character set that differs from table defaults *(only applies to MySQL)* (only applies to MySQL).
     *
     * @param string $phinxType The phinx type
     * @param array $attributes The attributes
     * @param array $columnData The column data
     *
     * @return array The attributes
     */
    private function getPhinxColumnEncoding(string $phinxType, array $attributes, array $columnData): array
    {
        $allowedTypes = [
            AdapterInterface::PHINX_TYPE_CHAR,
            AdapterInterface::PHINX_TYPE_STRING,
            AdapterInterface::PHINX_TYPE_TEXT,
        ];
        if (!in_array($phinxType, $allowedTypes, true)) {
            return $attributes;
        }

        if (!empty($columnData['CHARACTER_SET_NAME'])) {
            $attributes['encoding'] = $columnData['CHARACTER_SET_NAME'];
        }

        return $attributes;
    }

    /**
     * Generate phinx column options (comment).
     *
     * @param array $attributes The attributes
     * @param array $columnData The column data
     *
     * @return array The attributes
     */
    private function getPhinxColumnOptionsComment(array $attributes, array $columnData): array
    {
        // Set a text comment on the column
        if (!empty($columnData['COLUMN_COMMENT'])) {
            $attributes['comment'] = $columnData['COLUMN_COMMENT'];
        }

        return $attributes;
    }

    /**
     * Generate phinx column options (after).
     *
     * @param array $attributes
     * @param array $columnData
     * @param array $columns
     *
     * @return array Attributes
     */
    private function getPhinxColumnOptionsAfter(array $attributes, array $columnData, array $columns): array
    {
        $columnName = $columnData['COLUMN_NAME'];
        $after = null;
        foreach (array_keys($columns) as $column) {
            if ($column === $columnName) {
                break;
            }
            $after = $column;
        }
        if (!empty($after)) {
            $attributes['after'] = $after;
        }

        return $attributes;
    }
}
