<?php

return array(
    'dsn' => 'mysql:host=127.0.0.1;dbname=test',
    'username' => 'root',
    'password' => '',
    'options' => array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
    ),
    'schema_file' => __DIR__ . '/schema.php',
    'migration_path' => __DIR__
);
