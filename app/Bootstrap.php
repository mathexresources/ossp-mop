<?php

declare(strict_types=1);

namespace App;

require __DIR__ . '/../vendor/autoload.php';

$configurator = new \Nette\Bootstrap\Configurator();

$configurator->setDebugMode((bool) getenv('NETTE_DEBUG'));
$configurator->enableTracy(__DIR__ . '/../log');
$configurator->setTempDirectory(__DIR__ . '/../temp');

$configurator->createRobotLoader()
    ->addDirectory(__DIR__)
    ->register();

$configurator->addParameters([
    'dbHost'     => getenv('DB_HOST')     ?: 'database',
    'dbName'     => getenv('DB_NAME')     ?: 'app',
    'dbUser'     => getenv('DB_USER')     ?: 'app',
    'dbPassword' => getenv('DB_PASSWORD') ?: 'app',
    'appUrl'     => getenv('APP_URL')     ?: 'http://localhost:8080',
    'mailFrom'   => getenv('MAIL_FROM')   ?: 'SnapFix <noreply@localhost>',
    'smtpHost'     => getenv('SMTP_HOST')     ?: 'mailpit',
    'smtpPort'     => (int) (getenv('SMTP_PORT') ?: 1025),
    'smtpUser'     => getenv('SMTP_USER')     ?: '',
    'smtpPassword' => getenv('SMTP_PASSWORD') ?: '',
    'smtpSecure'   => getenv('SMTP_SECURE')   ?: null,
]);

$configurator->addConfig(__DIR__ . '/../config/common.neon');
$configurator->addConfig(__DIR__ . '/../config/local.neon');

return $configurator->createContainer();
