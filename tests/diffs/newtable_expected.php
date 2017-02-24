<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class MyNewMigration extends AbstractMigration
{
    public function change()
    {
        $this->table("newtable")->save();
        $this->execute("ALTER TABLE `newtable` ENGINE='InnoDB';");
        $this->execute("ALTER TABLE `newtable` COMMENT='';");
        if ($this->table('newtable')->hasColumn('id')) {
            $this->table("newtable")->changeColumn('id', 'integer', ['null' => false, 'limit' => MysqlAdapter::INT_REGULAR, 'precision' => 10, 'identity' => 'enable'])->update();
        } else {
            $this->table("newtable")->addColumn('id', 'integer', ['null' => false, 'limit' => MysqlAdapter::INT_REGULAR, 'precision' => 10, 'identity' => 'enable'])->update();
        }
    }
}
