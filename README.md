# Migrations
Migrations Builder for Phinx.

Currently Phinx (a migration library) cannot generate migrations automatically.
Phinx "only" generates a empty class with up and down functions. You still have to write the migration manually.

In reality, you should rarely need to write migrations manually, as the migrations library "should" generate migration classes automatically by comparing your schema mapping information (i.e. what your database should look like) with your actual current database structure.

# Installation

```
composer require odan/migrations
```

# Configuration
@todo

# Usage

## Generating Migrations Automatically

```
php migrations.php migration:generate
```
