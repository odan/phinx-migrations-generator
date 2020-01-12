<?php

namespace Odan\Migration\Test;

use Odan\Migration\Command\GenerateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @coversDefaultClass \Odan\Migration\Command\GenerateCommand
 */
class GenerateCommandTest extends TestCase
{
    /**
     * Test.
     *
     * @expectedException \Exception
     *
     * @return void
     */
    public function testGenerate()
    {
        $application = new Application();
        $application->add(new GenerateCommand());

        $command = $application->find('generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertRegExp('/.../', $commandTester->getDisplay());
    }
}
