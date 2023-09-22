<?php

use Phinx\Db\Adapter\MysqlAdapter;

class MyNewMigration extends \Phinx\Migration\AbstractMigration
{
    public function change()
    {
        $this->table('newtable', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => true,
            ])
            ->create();
    }
}
