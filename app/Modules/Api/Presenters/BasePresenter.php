<?php

declare(strict_types=1);

namespace App\Modules\Api\Presenters;

use Nette\Application\UI\Presenter;

/**
 * Base for all Api-module presenters.
 *
 * Provides helpers for JSON responses and common request guards.
 * All methods that send a response are typed `never` — they terminate
 * the request by throwing a Nette\Application\AbortException.
 */
abstract class BasePresenter extends Presenter
{
    /**
     * Sends a JSON error response and halts execution.
     *
     * @param  string  $message  Human-readable error message
     * @param  int     $httpCode HTTP status code (default 400)
     */
    protected function sendJsonError(string $message, int $httpCode = 400): never
    {
        $this->getHttpResponse()->setCode($httpCode);
        $this->sendJson(['success' => false, 'error' => $message]);
    }

    /**
     * Aborts with 405 if the request method is not POST.
     */
    protected function requirePost(): void
    {
        if (!$this->getHttpRequest()->isMethod('POST')) {
            $this->sendJsonError('Method not allowed.', 405);
        }
    }

    /**
     * Aborts with 400 if the request is missing the XMLHttpRequest header.
     * Acts as a lightweight CSRF mitigation for same-origin AJAX calls.
     */
    protected function requireXhr(): void
    {
        if ($this->getHttpRequest()->getHeader('X-Requested-With') !== 'XMLHttpRequest') {
            $this->sendJsonError('Bad request.', 400);
        }
    }

    /**
     * Aborts with 401 if no user is logged in.
     */
    protected function requireLogin(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->sendJsonError('Unauthorized.', 401);
        }
    }
}
