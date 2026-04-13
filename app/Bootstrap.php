<?php

declare(strict_types=1);

namespace App;

require __DIR__ . '/../vendor/autoload.php';

$configurator = new \Nette\Bootstrap\Configurator;

$configurator->setDebugMode(true);
$configurator->enableTracy(__DIR__ . '/../log');
$configurator->setTempDirectory(__DIR__ . '/../temp');

$configurator->createRobotLoader()
    ->addDirectory(__DIR__)
    ->register();

$configurator->addConfig(__DIR__ . '/../config/common.neon');
$configurator->addConfig(__DIR__ . '/../config/local.neon');

return $configurator->createContainer();
