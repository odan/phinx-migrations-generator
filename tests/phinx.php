<?php

// Framework bootstrap code here
//require_once __DIR__ . '/bootstrap.php';

// Get PDO object
$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=phinx_test;charset=utf8',
    'root',
    isset($_SERVER['GITHUB_ACTION']) ? 'root' : '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8 COLLATE utf8_unicode_ci',
    ]
);

// Get migration path for phinx classes
$migrationPath = __DIR__;

return [
    'paths' => [
        'migrations' => $migrationPath,
    ],
    //'table_prefix' => 'dev_',
    //'table_suffix' => '_v1',
    'foreign_keys' => true,
    'environments' => [
        //'default_migration_table' => 'my_migration_table',
        'default_database' => 'local',
        'local' => [
            // Database name
            'name' => $pdo->query('select database()')->fetchColumn(),
            'connection' => $pdo,
            //'adapter' => 'mysql',
            //'wrapper' => 'testwrapper',
            //'host' => 'localhost',
            //'name' => 'test',
            //'user' => 'root',
            // 'pass' => '',
            //'port' => 3306,
            //'table_prefix' => 'dev_',
            //'table_suffix' => '_v1',
        ],
    ],
];
