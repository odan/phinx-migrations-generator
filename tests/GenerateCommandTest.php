<?php

namespace Odan\Migration\Test;

use Exception;
use Odan\Migration\Command\GenerateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @coversDefaultClass \Odan\Migration\Command\GenerateCommand
 */
final class GenerateCommandTest extends TestCase
{
    /**
     * Test.
     *
     * @return void
     */
    public function testGenerate(): void
    {
        $this->expectException(Exception::class);

        $application = new Application();
        $application->add(new GenerateCommand());

        $command = $application->find('generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertMatchesRegularExpression('/.../', $commandTester->getDisplay());
    }
}
