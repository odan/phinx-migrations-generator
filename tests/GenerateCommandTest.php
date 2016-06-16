<?php

use Odan\Migration\Command\GenerateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @coversDefaultClass \Odan\Migration\Command\GenerateCommand
 */
class GenerateCommandTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Test
     *
     * @covers ::execute
     */
    public function testGenerate()
    {
        $application = new Application();
        $application->add(new GenerateCommand());

        $command = $application->find('migration:generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));

        $this->assertRegExp('/.../', $commandTester->getDisplay());
    }
}
