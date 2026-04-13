<?php

declare(strict_types=1);

$container = require __DIR__ . '/../app/Bootstrap.php';
$application = $container->getByType(\Nette\Application\Application::class);
$application->run();
