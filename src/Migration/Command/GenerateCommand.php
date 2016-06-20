<?php

namespace Odan\Migration\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{

    /**
     * configure
     */
    protected function configure()
    {
        $this->setName('migration:generate')
                ->setDescription('Generate migration')
                //->addArgument('config', InputArgument::OPTIONAL, 'Configuration file.', 'migrations-config.php')
                // php migrations.php migration:generate --config=myconfig.php
                ->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Configuration file.', 'migrations-config.php')
        ;
    }

    /**
     * Execute
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dbAdapter = new \Odan\Migration\Adapter\Database\MySqlAdapter();
        $phinxAdapter = new \Odan\Migration\Adapter\Generator\PhinxGenerator();
        $generator = new \Odan\Migration\Generator\MigrationGenertor($dbAdapter, $phinxAdapter, $input, $output);
        $generator->generate();
    }
}
