# Phinx migrations generator

Currently Phinx (a migration library) cannot generate migrations automatically.
Phinx "only" generates a empty class with up and down functions. You still have to write the migration manually.

In reality, you should rarely need to write migrations manually, as the migrations library "should" generate migration classes automatically by comparing your schema mapping information (i.e. what your database should look like) with your actual current database structure.

![Screenshot](https://github.com/odan/phinx-migrations-generator/blob/master/docs/images/screenshot01.jpg "Screenshot")

Generated migration

![Screenshot 2](https://github.com/odan/phinx-migrations-generator/blob/master/docs/images/screenshot02.jpg "Screenshot 2")

THIS IS A DEVELOPMENT PREVIEW - DO NOT USE IT IN PRODUCTION!

# Features

* Framework independent
* DBMS: MySQL
* Database: character set, collation
* Tables: create, update, remove, engine, comment, character set, collation
* Columns: create, update, remove
* Indexes: create, remove
* Foreign keys (experimental): create, remove

### Not supported

* MySQL [double] is not supported by phinx https://github.com/robmorgan/phinx/issues/498
* MySQL [year] is not supported by phinx. https://github.com/robmorgan/phinx/pull/704 | https://github.com/robmorgan/phinx/issues/551
* MySQL [bit] is not supported by phinx. https://github.com/robmorgan/phinx/pull/778
* MySQL enum values with special characters: https://github.com/robmorgan/phinx/issues/887
* Migration of contraint names (currently only auto generated): https://github.com/robmorgan/phinx/issues/823
* MySQL comments with special characters.

# Installation

```
composer require odan/phinx-migrations-generator
```

# Configuration

* Default configuration file: phinx-migrations-config.php

Example:

```php
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
```

Name | Type | Default | Description
--- | --- | --- | ---
dsn | string |  | Data source name (mysql:host=127.0.0.1;dbname=test)
username | string | | Database username
password | string | | Database password
options | array | | Database options
schema_file | string | schema.php | Database schema file (schema.php or schema.json)
migration_path | string | | Output directory for migration files

# Usage

## Generating migrations

```
cd bin
php phinx-migrations migration:generate
```

## Load custom config file

```
php phinx-migrationsmigration:generate --config=myconfig.php
```

## Alternative projects

https://github.com/robmorgan/phinx/issues/109#issuecomment-255297913

