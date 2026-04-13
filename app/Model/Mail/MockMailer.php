<?php

declare(strict_types=1);

namespace App\Model\Mail;

use Tracy\Debugger;

/**
 * Development-only mailer that writes every outgoing message to log/mail.log
 * instead of actually delivering it.  Replace with a real Nette\Mail\Mailer
 * implementation in production.
 */
final class MockMailer
{
    public function send(string $to, string $subject, string $body): void
    {
        $line = implode("\n", [
            str_repeat('-', 60),
            'TO:      ' . $to,
            'SUBJECT: ' . $subject,
            'BODY:',
            $body,
            '',
        ]);

        Debugger::log($line, 'mail');
    }
}
