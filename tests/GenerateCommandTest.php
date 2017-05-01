<?php

use Odan\Migration\Command\GenerateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @coversDefaultClass \Odan\Migration\Command\GenerateCommand
 */
class GenerateCommandTest extends \PHPUnit\Framework\TestCase
{

    /**
     * Test
     *
     * @covers ::execute
     * @covers ::read
     * @expectedException Exception
     */
    public function testGenerate()
    {
        $application = new Application();
        $application->add(new GenerateCommand());

        $command = $application->find('generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));

        $this->assertRegExp('/.../', $commandTester->getDisplay());
    }
}
