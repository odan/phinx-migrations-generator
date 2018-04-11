# Phinx migrations generator

Generate a migration by comparing your current database to your mapping information.

[![Latest Version on Packagist](https://img.shields.io/github/release/odan/phinx-migrations-generator.svg)](https://github.com/odan/phinx-migrations-generator/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)
[![Build Status](https://travis-ci.org/odan/phinx-migrations-generator.svg?branch=master)](https://travis-ci.org/odan/phinx-migrations-generator)
[![Code Coverage](https://scrutinizer-ci.com/g/odan/phinx-migrations-generator/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/odan/phinx-migrations-generator/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/odan/phinx-migrations-generator/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/odan/phinx-migrations-generator/?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/odan/phinx-migrations-generator.svg)](https://packagist.org/packages/odan/phinx-migrations-generator)


[Phinx](https://phinx.org/) cannot automatically generate migrations.
Phinx creates "only" a class with empty `up`, `down` or `change` functions. You still have to write the migration manually.

In reality, you should rarely have to write migrations manually because the migration library should automatically generate migration classes by comparing your schema mapping information (i.e. how your database should look like) with your current database structure.

## Features

* Framework independent
* DBMS: MySQL (only)
* Initial schema, schema diff
* Database: character set, collation
* Tables: create, update, remove, engine, comment, character set, collation
* Columns: create, update, remove
* Indexes: create, remove
* Foreign keys: create, remove, constraint name

## Install

Via Composer

```
$ composer require odan/phinx-migrations-generator --dev
```

## Usage

### Generating migrations

On the first run, an inital schema and a migration class is generated.
The `schema.php` file contains the previous database schema and is getting compared with the current schema.
Based on the difference, a Phinx migration class is generated.

Linux
```
$ vendor/bin/phinx-migrations generate
```

Windows
```
> vendor\bin\phinx-migrations generate
```

By executing the `generate` command again, only the difference to the last schema is generated.

## Parameters

Parameter | Values | Default | Description
--- | --- | --- | ---
--name | string | | The class name.
--overwrite | bool |  | Overwrite schema.php file.
--path <path> | string | (from phinx) | Specify the path in which to generate this migration.
--environment or -e | string | (from phinx) | The target environment.

### Generated migration (example)

Filename: `20170410194428_init.php`

```php
<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class Init extends AbstractMigration
{
    public function change()
    {
        $this->execute("ALTER DATABASE CHARACTER SET 'utf8';");
        $this->execute("ALTER DATABASE COLLATE='utf8_unicode_ci';");
        
        $this->table("users")->save();
        $this->execute("ALTER TABLE `users` ENGINE='InnoDB';");
        $this->execute("ALTER TABLE `users` COMMENT='Users Table';");
        $this->execute("ALTER TABLE `users` CHARSET='utf8';");
        $this->execute("ALTER TABLE `users` COLLATE='utf8_unicode_ci';");
        if ($this->table('users')->hasColumn('id')) {
            $this->table("users")->changeColumn('id', 'integer', ['null' => false, 'limit' => MysqlAdapter::INT_REGULAR, 'precision' => 10, 'identity' => 'enable'])->update();
        } else {
            $this->table("users")->addColumn('id', 'integer', ['null' => false, 'limit' => MysqlAdapter::INT_REGULAR, 'precision' => 10, 'identity' => 'enable'])->update();
        }
        $this->table('users')
            ->addColumn('username', 'string', ['null' => true, 'limit' => 255, 'collation' => "utf8_unicode_ci", 'encoding' => "utf8", 'after' => 'id'])
            ->addColumn('password', 'string', ['null' => true, 'limit' => 255, 'collation' => "utf8_unicode_ci", 'encoding' => "utf8", 'after' => 'username'])
            ->addColumn('email', 'string', ['null' => true, 'limit' => 255, 'collation' => "utf8_unicode_ci", 'encoding' => "utf8", 'after' => 'password'])
            ->addColumn('first_name', 'string', ['null' => true, 'limit' => 255, 'collation' => "utf8_unicode_ci", 'encoding' => "utf8", 'after' => 'email'])
            ->addColumn('last_name', 'string', ['null' => true, 'limit' => 255, 'collation' => "utf8_unicode_ci", 'encoding' => "utf8", 'after' => 'first_name'])
            ->addColumn('role', 'string', ['null' => true, 'limit' => 255, 'collation' => "utf8_unicode_ci", 'encoding' => "utf8", 'after' => 'last_name'])
            ->addColumn('locale', 'string', ['null' => true, 'limit' => 255, 'collation' => "utf8_unicode_ci", 'encoding' => "utf8", 'after' => 'role'])
            ->addColumn('disabled', 'boolean', ['null' => false, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY, 'precision' => 3, 'after' => 'locale'])
            ->addColumn('created', 'datetime', ['null' => true, 'after' => 'disabled'])
            ->addColumn('created_user_id', 'integer', ['null' => true, 'limit' => MysqlAdapter::INT_REGULAR, 'precision' => 10, 'after' => 'created'])
            ->addColumn('updated', 'datetime', ['null' => true, 'after' => 'created_user_id'])
            ->addColumn('updated_user_id', 'integer', ['null' => true, 'limit' => MysqlAdapter::INT_REGULAR, 'precision' => 10, 'after' => 'updated'])
            ->addColumn('deleted', 'datetime', ['null' => true, 'after' => 'updated_user_id'])
            ->addColumn('deleted_user_id', 'integer', ['null' => true, 'limit' => MysqlAdapter::INT_REGULAR, 'precision' => 10, 'after' => 'deleted'])
            ->update();
        if($this->table('users')->hasIndex('username')) {
            $this->table("users")->removeIndexByName('username');
        }
        $this->table("users")->addIndex(['username'], ['name' => "username", 'unique' => true])->save();
        if($this->table('users')->hasIndex('created_user_id')) {
            $this->table("users")->removeIndexByName('created_user_id');
        }
        $this->table("users")->addIndex(['created_user_id'], ['name' => "created_user_id", 'unique' => false])->save();
        if($this->table('users')->hasIndex('updated_user_id')) {
            $this->table("users")->removeIndexByName('updated_user_id');
        }
        $this->table("users")->addIndex(['updated_user_id'], ['name' => "updated_user_id", 'unique' => false])->save();
        if($this->table('users')->hasIndex('deleted_user_id')) {
            $this->table("users")->removeIndexByName('deleted_user_id');
        }
        $this->table("users")->addIndex(['deleted_user_id'], ['name' => "deleted_user_id", 'unique' => false])->save();
    }
}
```


### Running migrations

The [Phinx migrate command](http://docs.phinx.org/en/latest/commands.html#the-migrate-command) runs all of the available migrations.

Linux
```
$ vendor/bin/phinx migrate
```

Windows
```
> vendor\bin\phinx migrate
```

## Configuration

The phinx-migrations-generator uses the configuration of phinx.

## Migration configuration

Parameter | Values | Default | Description
--- | --- | --- | ---
foreign_keys | bool | false | Enable or disable foreign key migrations.

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

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
