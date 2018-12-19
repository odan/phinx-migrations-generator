<?php
namespace Odan\Migration\Adapter\Database;

/**
 * Interface for all Database adapters
 *
 */
interface SchemaAdapterInterface
{

    /**
     * Load current database schema.
     *
     * @return array
     */
    public function getSchema(): array;

    /**
     * Quote value.
     *
     * @param string|null $value
     *
     * @return string
     */
    public function quote($value): string;

    /**
     * Escape identifier (column, table) with backtick.
     *
     * @see: http://dev.mysql.com/doc/refman/5.0/en/identifiers.html
     *
     * @param string $value
     * @param string $quote
     *
     * @return string identifier escaped string
     */
    public function ident($value, $quote = '`'): string;

    /**
     * Get foreign keys.
     *
     * @param string $tableName
     *
     * @return array|null
     */
    public function getForeignKeys($tableName): ?array;
}
