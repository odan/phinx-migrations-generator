# Phinx migrations generator

Generates [Phinx](https://phinx.org/) migrations by comparing your current database with your schema information.

[![Latest Version on Packagist](https://img.shields.io/github/release/odan/phinx-migrations-generator.svg)](https://packagist.org/packages/odan/phinx-migrations-generator)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)
[![Build Status](https://travis-ci.org/odan/phinx-migrations-generator.svg?branch=master)](https://travis-ci.org/odan/phinx-migrations-generator)
[![Code Coverage](https://scrutinizer-ci.com/g/odan/phinx-migrations-generator/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/odan/phinx-migrations-generator/?branch=master)
[![Quality Score](https://scrutinizer-ci.com/g/odan/phinx-migrations-generator/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/odan/phinx-migrations-generator/?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/odan/phinx-migrations-generator.svg)](https://packagist.org/packages/odan/phinx-migrations-generator/stats)

## Requirements

* PHP 7.1+

## Features

* Framework independent
* DBMS: MySQL 5.7+, MySQL 8, MariaDB is only partially supported
* Initial schema 
* Schema difference
* Database: character set, collation
* Tables: create, update, remove, engine, comment, character set, collation
* Columns: create, update, remove
* Indexes: create, remove
* Foreign keys: create, remove, constraint name

> **Warning:** You should not use [Phinx](https://phinx.org/) with MySQL 8, as it may cause unexpected results.

## Install

Via Composer

```
$ composer require odan/phinx-migrations-generator --dev
```

## Usage

### Generating migrations

The first run generates an initial schema and a migration class.
The file `schema.php` contains the previous database schema and is compared with the current schema.
Based on the difference, a Phinx migration class is generated.

```
$ vendor/bin/phinx-migrations generate
```

When the `generate` command is executed again, only the difference to the last schema is generated.

## Parameters

Parameter | Values | Default | Description
--- | --- | --- | ---
--name | string | | The class name.
--overwrite | bool |  | Overwrite schema.php file.
--path <path> | string | (from phinx) | Specify the path in which to generate this migration.
--environment or -e | string | (from phinx) | The target environment.
--configuration or -c | string | (from phinx) | The configuration file e.g. `config/phinx.php`

### Running migrations

The [Phinx migrate command](http://docs.phinx.org/en/latest/commands.html#the-migrate-command) 
runs all of the available migrations.

```
$ vendor/bin/phinx migrate
```

## Configuration

The phinx-migrations-generator uses the configuration of phinx.

## Migration configuration

Parameter | Values | Default | Description
--- | --- | --- | ---
foreign_keys | bool | false | Enable or disable foreign key migrations.
default_migration_prefix | string | null | If specified, in the absence of the name parameter, the default migration name will be offered with this prefix and a random hash at the end.
generate_migration_name | bool | false | If enabled, a random migration name will be generated. The user will not be prompted for a migration name anymore. The parameter `default_migration_prefix` must be specified. The `--name` parameter can overwrite this setting.
mark_generated_migration | bool | true | Enable or disable marking the migration as applied after creation.
migration_base_class | string | `\Phinx\Migration\AbstractMigration` | Sets up base class of created migration.
schema_file | string | `%%PHINX_CONFIG_DIR%%/db/` `migrations/schema.php` | Specifies the location for saving the schema file.

### Example configuration

Filename: `phinx.php` (in your project root directory)

```php
<?php

// Framework bootstrap code here
require_once __DIR__ . '/config/bootstrap.php';

// Get PDO object
$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=test;charset=utf8', 'root', '',
    array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8 COLLATE utf8_unicode_ci',
    )
);

// Get migration path for phinx classes
$migrationPath = __DIR__ . '/resources/migrations';

return [
    'paths' => [
        'migrations' => $migrationPath,
    ],
    'foreign_keys' => false,
    'default_migration_prefix' => 'db_change_',
    'mark_generated_migration' => true,
    'migration_base_class' => \Phinx\Migration\AbstractMigration::class,
    'environments' => [
        'default_database' => 'local',
        'local' => [
            // Database name
            'name' => $pdo->query('select database()')->fetchColumn(),
            'connection' => $pdo,
        ]
    ]
];
```

## Testing

```bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
