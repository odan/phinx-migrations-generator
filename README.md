# Phinx migrations generator

[![Latest Version](https://img.shields.io/github/release/odan/phinx-migrations-generator.svg)](https://github.com/loadsys/odan/phinx-migrations-generator/releases)
[![Build Status](https://travis-ci.org/odan/phinx-migrations-generator.svg?branch=master)](https://travis-ci.org/odan/phinx-migrations-generator)
[![Crutinizer](https://img.shields.io/scrutinizer/g/odan/phinx-migrations-generator.svg)](https://scrutinizer-ci.com/g/odan/phinx-migrations-generator)
[![codecov](https://codecov.io/gh/odan/phinx-migrations-generator/branch/master/graph/badge.svg)](https://codecov.io/gh/odan/phinx-migrations-generator)
[![StyleCI](https://styleci.io/repos/61276581/shield?style=flat)](https://styleci.io/repos/61276581)
[![Total Downloads](https://img.shields.io/packagist/dt/odan/phinx-migrations-generator.svg)](https://packagist.org/packages/odan/phinx-migrations-generator)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)

Currently Phinx (a migration library) cannot generate migrations automatically.
Phinx "only" generates a empty class with up and down functions. You still have to write the migration manually.

In reality, you should rarely need to write migrations manually, as the migrations library "should" generate migration classes automatically by comparing your schema mapping information (i.e. what your database should look like) with your actual current database structure.

![Screenshot](https://github.com/odan/phinx-migrations-generator/blob/master/docs/images/screenshot01.jpg "Screenshot")

Generated migration

![Screenshot 2](https://github.com/odan/phinx-migrations-generator/blob/master/docs/images/screenshot02.jpg "Screenshot 2")

## Features

* Framework independent
* DBMS: MySQL
* Initial schema, schema diff
* Database: character set, collation
* Tables: create, update, remove, engine, comment, character set, collation
* Columns: create, update, remove
* Indexes: create, remove
* Foreign keys (experimental): create, remove, constraint name

## Install

Via Composer

```
$ composer require odan/phinx-migrations-generator
```

## Usage

### Generating migrations

On the first run, an inital schema and a migration class is generated.
The `schema.php` file contains the previous database schema and is getting compared with the the current schema.
Based on the difference, a Phinx migration class is generated.

```
$ vendor/bin/phinx-migrations generate
```

By executing the `generate` command again, only the difference to the last schema is generated.

### Running migrations

The [Phinx migrate command](http://docs.phinx.org/en/latest/commands.html#the-migrate-command) runs all of the available migrations.

```
$ vendor/bin/phinx-migrations migrate
```

## Configuration

* Not required. The phinx-migrations-generator uses the configuration of phinx.

## Parameters

Parameter | Values | Default | Description
--- | --- | --- | ---
--path <path> | string | (from phinx) | Specify the path in which to generate this migration.
--environment or -e | string | (from phinx) | The target environment.

## Todo
 
* Add option `--name` for the class name.
* Add option `--overwrite`.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
