<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use Nette\Application\Responses\CallbackResponse;
use Nette\Application\UI\Presenter;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
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
            $this->setView((string) $code);
            $this->template->title = $code === 404 ? 'Page Not Found' : 'Error';
            $this->template->code = $code;
        } else {
            $this->logger->log($exception, ILogger::EXCEPTION);
            $this->setView('500');
            $this->template->title = 'Server Error';
            $this->template->code = 500;
        }
    }
}
