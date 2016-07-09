# Migrations
Migrations Builder for Phinx.

Currently Phinx (a migration library) cannot generate migrations automatically.
Phinx "only" generates a empty class with up and down functions. You still have to write the migration manually.

In reality, you should rarely need to write migrations manually, as the migrations library "should" generate migration classes automatically by comparing your schema mapping information (i.e. what your database should look like) with your actual current database structure.

![Screenshot](https://github.com/odan/migrations/blob/master/docs/images/screenshot01.jpg "Screenshot")

Generated migration

![Screenshot 2](https://github.com/odan/migrations/blob/master/docs/images/screenshot02.jpg "Screenshot 2")

THIS IS A DEVELOPMENT PREVIEW - DO NOT USE IT IN PRODUCTION!

# Installation

```
git clone https://github.com/odan/migrations.git
cd migrations
composer install --no-dev
```

# Integration

```
composer require odan/migrations
```

# Configuration

* Default configuration file: migrations-config.php

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
php migrations.php migration:generate
```

## Load custom config file

```
php migrations.php migration:generate --config=myconfig.php
```
