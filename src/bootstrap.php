<?php declare(strict_types = 1);

namespace Kilvn\GethTokenJsonRpcPhpClient\Tests;

require __DIR__ . '/../vendor/autoload.php';

use Tester\Assert;
use Tester\Environment;
use function class_exists;
use function date_default_timezone_set;

if (!class_exists(Assert::class)) {
    echo "Install Nette Tester using `composer update --dev`\n";
    exit(1);
}

date_default_timezone_set('Europe/Prague');

Environment::setup();
