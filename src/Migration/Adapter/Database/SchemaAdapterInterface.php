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
     * @see: http://dev.mysql.com/doc/refman/5.7/en/identifiers.html
     *
     * @param string $value
     *
     * @return string identifier escaped string
     */
    public function ident($value): string;
}
