<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use Nette\Application\UI\Presenter;
use Tracy\ILogger;

final class ErrorPresenter extends Presenter
{
    public function __construct(
        private readonly ILogger $logger,
    ) {
        parent::__construct();
    }

    public function renderDefault(\Throwable $exception): void
    {
        if ($exception instanceof \Nette\Application\BadRequestException) {
            $code = $exception->getHttpCode();

            // Map to known views; fall through to 500 for unknown 4xx codes
            $knownViews = [403, 404];
            $view = in_array($code, $knownViews, true) ? (string) $code : '500';

            $this->setView($view);
            $this->template->code  = $code;
            $this->template->title = match ($code) {
                403     => 'Access Denied',
                404     => 'Page Not Found',
                default => 'Error',
            };
        } else {
            $this->logger->log($exception, ILogger::EXCEPTION);
            $this->setView('500');
            $this->template->code  = 500;
            $this->template->title = 'Server Error';
        }
    }
}
