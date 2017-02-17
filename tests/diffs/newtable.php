<?php

return array(
    'tables' =>
    array(
        'newtable' =>
        array(
            'table' =>
            array(
                'table_name' => 'newtable',
                'engine' => 'InnoDB',
                'table_comment' => '',
            ),
            'columns' =>
            array(
                'id' =>
                array(
                    'TABLE_CATALOG' => 'def',
                    'TABLE_SCHEMA' => 'test',
                    'TABLE_NAME' => 'newtable2',
                    'COLUMN_NAME' => 'id',
                    'ORDINAL_POSITION' => '1',
                    'COLUMN_DEFAULT' => null,
                    'IS_NULLABLE' => 'NO',
                    'DATA_TYPE' => 'int',
                    'CHARACTER_MAXIMUM_LENGTH' => null,
                    'CHARACTER_OCTET_LENGTH' => null,
                    'NUMERIC_PRECISION' => '10',
                    'NUMERIC_SCALE' => '0',
                    'DATETIME_PRECISION' => null,
                    'CHARACTER_SET_NAME' => null,
                    'COLLATION_NAME' => null,
                    'COLUMN_TYPE' => 'int(11)',
                    'COLUMN_KEY' => 'PRI',
                    'EXTRA' => 'auto_increment',
                    'PRIVILEGES' => 'select,insert,update,references',
                    'COLUMN_COMMENT' => '',
                ),
            ),
            'indexes' =>
            array(
                'PRIMARY' =>
                array(
                    'Table' => 'newtable',
                    'Non_unique' => '0',
                    'Key_name' => 'PRIMARY',
                    'Seq_in_index' => '1',
                    'Column_name' => 'id',
                    'Collation' => 'A',
                    'Cardinality' => '0',
                    'Sub_part' => null,
                    'Packed' => null,
                    'Null' => '',
                    'Index_type' => 'BTREE',
                    'Comment' => '',
                    'Index_comment' => '',
                ),
            ),
            'foreign_keys' =>
            array(
            ),
        ),
    ),
);
