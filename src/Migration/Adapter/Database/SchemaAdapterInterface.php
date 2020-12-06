<?php

namespace Odan\Migration\Adapter\Database;

/**
 * Interface for all Database adapters.
 */
interface SchemaAdapterInterface
{
    /**
     * Load current database schema.
     *
     * @param array|null $tableNames
     *
     * @return array
     */
    public function getSchema($tableNames = null): array;

    /**
     * Get database version.
     *
     * @return string The database version
     */
    public function getVersion(): string;

    /**
     * Quote value.
     *
     * @param string|null $value
     *
     * @return string
     */
    public function quote(?string $value): string;

    /**
     * Escape identifier (column, table) with backtick.
     *
     * @see: http://dev.mysql.com/doc/refman/5.7/en/identifiers.html
     *
     * @param string $value
     *
     * @return string identifier escaped string
     */
    public function ident(string $value): string;
}
