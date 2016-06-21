# Migrations
Migrations Builder for Phinx.

Currently Phinx (a migration library) cannot generate migrations automatically.
Phinx "only" generates a empty class with up and down functions. You still have to write the migration manually.

In reality, you should rarely need to write migrations manually, as the migrations library "should" generate migration classes automatically by comparing your schema mapping information (i.e. what your database should look like) with your actual current database structure.

# Installation

```
git clone https://github.com/odan/migrations.git
cd migrations
composer install
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
    'schema_file' => __DIR__ . '/schema.php'
);
```

Name | Type | Default | Description
--- | --- | --- | ---
dsn | string |  | Data source name (mysql:host=127.0.0.1;dbname=test)
username | string | | Database username
password | string | | Database password
options | array | | Database options
schema_file | string | schema.php | Database schema file (schema.php or schema.json)


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
