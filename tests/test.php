<?php

// Debug from console
// set XDEBUG_CONFIG="idekey=xdebug"
// php test.php

require_once __DIR__ . '/../vendor/autoload.php';

$phpunit = new \PHPUnit\TextUI\TestRunner();

try {
    echo "<pre>\n";
    $testResults = $phpunit->doRun($phpunit->getTest(__DIR__, '', 'Test.php'), array(), false);
    echo "</pre>\n";
} catch (\PHPUnit\Framework\Exception $e) {
    print $e->getMessage() . "\n";
    echo "Unit tests failed.";
}
