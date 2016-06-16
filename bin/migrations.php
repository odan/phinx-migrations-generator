#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Odan\Migration\Command\GenerateCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new GenerateCommand());
$application->run();
