#!/usr/bin/env php
<?php
// Debugging: SET XDEBUG_CONFIG=idekey=netbeans-xdebug
if (!(class_exists('Symfony\\Component\\Console\\Application') && class_exists('Odan\\Migration\\Command\\GenerateCommand'))) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use Odan\Migration\Command\GenerateCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new GenerateCommand());
$application->run();
