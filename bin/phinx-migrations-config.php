<?php

return array(
    'dsn' => 'mysql:host=127.0.0.1;dbname=test;charset=utf8mb4',
    'username' => 'root',
    'password' => '',
    'options' => array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'
    ),
    'schema_file' => __DIR__ . '/schema.php',
    'migration_path' => __DIR__
);
