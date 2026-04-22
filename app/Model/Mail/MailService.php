<?php

declare(strict_types=1);

namespace App\Model\Mail;

use App\Model\Database\RowType;
use App\Model\Repository\EmailLogRepository;
use App\Model\Repository\ItemRepository;
use App\Model\Repository\UserRepository;
use Latte\Engine;
use Nette\Database\Table\ActiveRow;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Tracy\Debugger;

final class MailService
{
    private const APP_NAME = 'OSSP MOP';

    private Engine $latte;

    private string $templateDir;

    public function __construct(
        private readonly Mailer             $mailer,
        private readonly EmailLogRepository $emailLog,
        private readonly UserRepository     $userRepository,
        private readonly ItemRepository     $itemRepository,
        private readonly string             $from,
        private readonly string             $appUrl,
        string                              $tempDir,
    ) {
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $this->latte = new Engine();
        $this->latte->setTempDirectory($tempDir);

        $this->templateDir = dirname(__DIR__, 2) . '/Modules/Mail/templates';
    }

    public function sendWelcome(ActiveRow $user): void
    {
        $this->doSend(
            to:       RowType::string($user->email),
            subject:  'Welcome to ' . self::APP_NAME . ' — your account is pending approval',
            type:     'welcome',
            template: 'welcome',
            params:   ['user' => $user],
        );
    }

    /** @param ActiveRow[] $admins */
    public function sendAdminNewPending(ActiveRow $newUser, array $admins): void
    {
        $firstName = RowType::string($newUser->first_name);
        $lastName = RowType::string($newUser->last_name);

        foreach ($admins as $admin) {
            $this->doSend(
                to:       RowType::string($admin->email),
                subject:  "New user registration pending approval — {$firstName} {$lastName}",
                type:     'admin_new_pending',
                template: 'adminNewPending',
                params:   ['newUser' => $newUser, 'admin' => $admin],
            );
        }
    }

    public function sendApproved(ActiveRow $user): void
    {
        $this->doSend(
            to:       RowType::string($user->email),
            subject:  'Your account has been approved — you can now log in',
            type:     'approved',
            template: 'approved',
            params:   ['user' => $user],
        );
    }

    public function sendRejected(ActiveRow $user, ?string $reason): void
    {
        $this->doSend(
            to:       RowType::string($user->email),
            subject:  'Your account registration was not approved',
            type:     'rejected',
            template: 'rejected',
            params:   ['user' => $user, 'reason' => $reason],
        );
    }

    public function sendPasswordChanged(ActiveRow $user): void
    {
        $this->doSend(
            to:       RowType::string($user->email),
            subject:  'Your password has been changed',
            type:     'password_changed',
            template: 'passwordChanged',
            params:   ['user' => $user],
        );
    }

    /** @param ActiveRow[] $supportUsers */
    public function sendTicketCreated(ActiveRow $ticket, array $supportUsers): void
    {
        if (empty($supportUsers)) {
            return;
        }

        $itemName    = $this->resolveItemName(RowType::int($ticket->item_id));
        $creatorName = $this->resolveUserName(RowType::int($ticket->created_by));
        $ticketId    = RowType::int($ticket->id);
        $ticketTitle = RowType::string($ticket->title);

        foreach ($supportUsers as $user) {
            $this->doSend(
                to:       RowType::string($user->email),
                subject:  "New ticket #{$ticketId} — {$ticketTitle}",
                type:     'ticket_created',
                template: 'ticketCreated',
                params:   [
                    'ticket'      => $ticket,
                    'itemName'    => $itemName,
                    'creatorName' => $creatorName,
                ],
            );
        }
    }

    public function sendTicketAssigned(ActiveRow $ticket, ActiveRow $assignee): void
    {
        $itemName = $this->resolveItemName(RowType::int($ticket->item_id));
        $ticketId = RowType::int($ticket->id);

        $this->doSend(
            to:       RowType::string($assignee->email),
            subject:  "Ticket #{$ticketId} has been assigned to you",
            type:     'ticket_assigned',
            template: 'ticketAssigned',
            params:   [
                'ticket'   => $ticket,
                'assignee' => $assignee,
                'itemName' => $itemName,
            ],
        );
    }

    public function sendTicketStatusChanged(
        ActiveRow $ticket,
        ActiveRow $creator,
        string $oldStatus,
        int $changedBy = 0,
    ): void {
        $changedByName = $changedBy > 0
            ? $this->resolveUserName($changedBy)
            : 'System';

        $ticketId     = RowType::int($ticket->id);
        $ticketStatus = RowType::string($ticket->status);

        $this->doSend(
            to:       RowType::string($creator->email),
            subject:  "Ticket #{$ticketId} status updated to " . $this->statusLabel($ticketStatus),
            type:     'ticket_status_changed',
            template: 'ticketStatusChanged',
            params:   [
                'ticket'        => $ticket,
                'oldStatus'     => $this->statusLabel($oldStatus),
                'newStatus'     => $this->statusLabel($ticketStatus),
                'changedByName' => $changedByName,
            ],
        );
    }

    public function sendDamagePointAdded(
        ActiveRow $ticket,
        ActiveRow $assignee,
        string $description,
    ): void {
        $ticketId = RowType::int($ticket->id);

        $this->doSend(
            to:       RowType::string($assignee->email),
            subject:  "New damage point added to ticket #{$ticketId}",
            type:     'damage_point_added',
            template: 'damagePointAdded',
            params:   [
                'ticket'      => $ticket,
                'description' => $description,
            ],
        );
    }

    public function sendServiceHistoryAdded(
        ActiveRow $ticket,
        ActiveRow $creator,
        string $serviceDescription,
        string $addedByName,
    ): void {
        $ticketId = RowType::int($ticket->id);

        $this->doSend(
            to:       RowType::string($creator->email),
            subject:  "Service record added to your ticket #{$ticketId}",
            type:     'service_history_added',
            template: 'serviceHistoryAdded',
            params:   [
                'ticket'             => $ticket,
                'serviceDescription' => $serviceDescription,
                'addedByName'        => $addedByName,
            ],
        );
    }

    /** @param array<string, mixed> $params */
    private function doSend(
        string $to,
        string $subject,
        string $type,
        string $template,
        array $params,
    ): void {
        $params['appName'] = self::APP_NAME;
        $params['appUrl']  = $this->appUrl;

        try {
            $html = $this->latte->renderToString(
                $this->templateDir . '/' . $template . '.latte',
                $params,
            );

            $message = new Message();
            $message->setFrom($this->from);
            $message->addTo($to);
            $message->setSubject($subject);
            $message->setHtmlBody($html);

            $this->mailer->send($message);

            $this->emailLog->logEmail($to, $subject, $type, 'sent', null);
        } catch (\Throwable $e) {
            $this->emailLog->logEmail($to, $subject, $type, 'failed', $e->getMessage());
            Debugger::log($e, 'mail-error');
        }
    }

    private function resolveItemName(int $itemId): string
    {
        try {
            $item = $this->itemRepository->findById($itemId);
            return $item ? RowType::string($item->name) : "Item #{$itemId}";
        } catch (\Throwable) {
            return "Item #{$itemId}";
        }
    }

    private function resolveUserName(int $userId): string
    {
        try {
            $user = $this->userRepository->findById($userId);
            return $user
                ? trim(RowType::string($user->first_name) . ' ' . RowType::string($user->last_name))
                : "User #{$userId}";
        } catch (\Throwable) {
            return "User #{$userId}";
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'closed'      => 'Closed',
            default       => ucfirst($status),
        };
    }
}
