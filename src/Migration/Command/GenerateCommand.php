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
        $this
                ->setName('migration:generate')
                ->setDescription('Generate migration')
                ->addArgument(
                        'name', InputArgument::OPTIONAL, 'Who do you want to greet?'
                )
                ->addOption(
                        'yell', null, InputOption::VALUE_NONE, 'If set, the task will yell in uppercase letters'
                )
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
        //$name = $input->getArgument('name');
        //$output->writeln('Generate migration');

        if ($input->getOption('yell')) {
            $text = strtoupper($text);
        }

        $dbAdapter = new \Odan\Migration\Adapter\Database\MySqlAdapter();
        $phinxAdapter = new \Odan\Migration\Adapter\Generator\PhinxGenerator();
        $generator = new \Odan\Migration\Generator\MigrationGenertor($dbAdapter, $phinxAdapter);
        $result = $generator->generate([]);
        $output->writeln($result);

        $output->writeln('Comparing schema.php to the database...');
        $output->writeln('No database changes detected.');

        //$output->writeln('Generate migration finished');
    }
}
