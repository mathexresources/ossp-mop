<?php

declare(strict_types=1);

namespace App\Model\Mail;

use App\Model\Repository\EmailLogRepository;
use App\Model\Repository\ItemRepository;
use App\Model\Repository\UserRepository;
use Latte\Engine;
use Nette\Database\Table\ActiveRow;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Tracy\Debugger;

/**
 * Central email-sending service.
 *
 * Every outgoing email goes through this class — no presenter or facade
 * sends directly to the Mailer.  All public methods are non-blocking:
 * exceptions are caught, logged to email_log, and swallowed so that a
 * mail failure can never crash a user-facing request.
 *
 * Templates live in app/Modules/Mail/templates/*.latte and use a shared
 * base layout (layout.latte) with inline CSS for email-client compatibility.
 */
final class MailService
{
    private const APP_NAME = 'OSSP MOP';

    /** Latte engine used exclusively for rendering email templates. */
    private Engine $latte;

    /** Absolute path to the email template directory. */
    private string $templateDir;

    public function __construct(
        private readonly Mailer               $mailer,
        private readonly EmailLogRepository   $emailLog,
        private readonly UserRepository       $userRepository,
        private readonly ItemRepository       $itemRepository,
        private readonly string               $from,
        private readonly string               $appUrl,
        string                                $tempDir,
    ) {
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $this->latte = new Engine();
        $this->latte->setTempDirectory($tempDir);

        // MailService is at app/Model/Mail/ → two levels up gives app/
        $this->templateDir = dirname(__DIR__, 2) . '/Modules/Mail/templates';
    }

    // ==================================================================
    //  User lifecycle emails
    // ==================================================================

    /**
     * Welcome email sent to the newly registered user.
     * Informs them their account is pending admin approval.
     */
    public function sendWelcome(ActiveRow $user): void
    {
        $this->doSend(
            to:       (string) $user->email,
            subject:  'Welcome to ' . self::APP_NAME . ' — your account is pending approval',
            type:     'welcome',
            template: 'welcome',
            params:   ['user' => $user],
        );
    }

    /**
     * Sends one email per admin notifying them of a new pending registration.
     *
     * @param ActiveRow   $newUser The newly registered user
     * @param ActiveRow[] $admins  Array of admin ActiveRow objects
     */
    public function sendAdminNewPending(ActiveRow $newUser, array $admins): void
    {
        foreach ($admins as $admin) {
            $this->doSend(
                to:       (string) $admin->email,
                subject:  'New user registration pending approval — '
                              . $newUser->first_name . ' ' . $newUser->last_name,
                type:     'admin_new_pending',
                template: 'adminNewPending',
                params:   ['newUser' => $newUser, 'admin' => $admin],
            );
        }
    }

    /**
     * Account-approved email sent to the user.
     */
    public function sendApproved(ActiveRow $user): void
    {
        $this->doSend(
            to:       (string) $user->email,
            subject:  'Your account has been approved — you can now log in',
            type:     'approved',
            template: 'approved',
            params:   ['user' => $user],
        );
    }

    /**
     * Account-rejected email sent to the user, optionally including the reason.
     */
    public function sendRejected(ActiveRow $user, ?string $reason): void
    {
        $this->doSend(
            to:       (string) $user->email,
            subject:  'Your account registration was not approved',
            type:     'rejected',
            template: 'rejected',
            params:   ['user' => $user, 'reason' => $reason],
        );
    }

    /**
     * Password-changed notice sent to the user after an admin reset.
     */
    public function sendPasswordChanged(ActiveRow $user): void
    {
        $this->doSend(
            to:       (string) $user->email,
            subject:  'Your password has been changed',
            type:     'password_changed',
            template: 'passwordChanged',
            params:   ['user' => $user],
        );
    }

    // ==================================================================
    //  Ticket emails
    // ==================================================================

    /**
     * Notifies all support users of a newly created ticket.
     *
     * @param ActiveRow   $ticket       The new ticket
     * @param ActiveRow[] $supportUsers Array of support user ActiveRows
     */
    public function sendTicketCreated(ActiveRow $ticket, array $supportUsers): void
    {
        if (empty($supportUsers)) {
            return;
        }

        $itemName    = $this->resolveItemName((int) $ticket->item_id);
        $creatorName = $this->resolveUserName((int) $ticket->created_by);

        foreach ($supportUsers as $user) {
            $this->doSend(
                to:       (string) $user->email,
                subject:  "New ticket #{$ticket->id} — {$ticket->title}",
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

    /**
     * Notifies the assigned support user that a ticket has been assigned to them.
     */
    public function sendTicketAssigned(ActiveRow $ticket, ActiveRow $assignee): void
    {
        $itemName = $this->resolveItemName((int) $ticket->item_id);

        $this->doSend(
            to:       (string) $assignee->email,
            subject:  "Ticket #{$ticket->id} has been assigned to you",
            type:     'ticket_assigned',
            template: 'ticketAssigned',
            params:   [
                'ticket'   => $ticket,
                'assignee' => $assignee,
                'itemName' => $itemName,
            ],
        );
    }

    /**
     * Notifies the ticket creator that the ticket's status has changed.
     *
     * @param ActiveRow $ticket    The ticket (status already updated in DB)
     * @param ActiveRow $creator   The ticket's creator
     * @param string    $oldStatus Previous status value
     * @param int       $changedBy User ID of the person who changed the status (0 = unknown)
     */
    public function sendTicketStatusChanged(
        ActiveRow $ticket,
        ActiveRow $creator,
        string    $oldStatus,
        int       $changedBy = 0,
    ): void {
        $changedByName = $changedBy > 0
            ? $this->resolveUserName($changedBy)
            : 'System';

        $this->doSend(
            to:       (string) $creator->email,
            subject:  "Ticket #{$ticket->id} status updated to "
                          . $this->statusLabel((string) $ticket->status),
            type:     'ticket_status_changed',
            template: 'ticketStatusChanged',
            params:   [
                'ticket'        => $ticket,
                'oldStatus'     => $this->statusLabel($oldStatus),
                'newStatus'     => $this->statusLabel((string) $ticket->status),
                'changedByName' => $changedByName,
            ],
        );
    }

    /**
     * Notifies the assigned support user that a damage point was added to their ticket.
     *
     * @param ActiveRow $ticket      The ticket the point belongs to
     * @param ActiveRow $assignee    The assigned support user to notify
     * @param string    $description Description of the damage point
     */
    public function sendDamagePointAdded(
        ActiveRow $ticket,
        ActiveRow $assignee,
        string    $description,
    ): void {
        $this->doSend(
            to:       (string) $assignee->email,
            subject:  "New damage point added to ticket #{$ticket->id}",
            type:     'damage_point_added',
            template: 'damagePointAdded',
            params:   [
                'ticket'      => $ticket,
                'description' => $description,
            ],
        );
    }

    /**
     * Notifies the ticket creator that a service record was added to the related item.
     *
     * @param ActiveRow $ticket             The ticket whose item received a service record
     * @param ActiveRow $creator            The ticket's creator (recipient)
     * @param string    $serviceDescription The service record description
     * @param string    $addedByName        Full name of the person who added the record
     */
    public function sendServiceHistoryAdded(
        ActiveRow $ticket,
        ActiveRow $creator,
        string    $serviceDescription,
        string    $addedByName,
    ): void {
        $this->doSend(
            to:       (string) $creator->email,
            subject:  "Service record added to your ticket #{$ticket->id}",
            type:     'service_history_added',
            template: 'serviceHistoryAdded',
            params:   [
                'ticket'             => $ticket,
                'serviceDescription' => $serviceDescription,
                'addedByName'        => $addedByName,
            ],
        );
    }

    // ==================================================================
    //  Internal helpers
    // ==================================================================

    /**
     * Renders a Latte template to HTML, builds a Message, and delivers it.
     * Logs every attempt (success or failure) to email_log.
     * Never throws — all exceptions are caught and swallowed.
     */
    private function doSend(
        string $to,
        string $subject,
        string $type,
        string $template,
        array  $params,
    ): void {
        // Inject common variables available in every template.
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

    /**
     * Resolves an item's display name from its ID.
     * Returns a fallback string on any error.
     */
    private function resolveItemName(int $itemId): string
    {
        try {
            $item = $this->itemRepository->findById($itemId);
            return $item ? (string) $item->name : "Item #{$itemId}";
        } catch (\Throwable) {
            return "Item #{$itemId}";
        }
    }

    /**
     * Resolves a user's full name from their ID.
     * Returns a fallback string on any error.
     */
    private function resolveUserName(int $userId): string
    {
        try {
            $user = $this->userRepository->findById($userId);
            return $user
                ? trim($user->first_name . ' ' . $user->last_name)
                : "User #{$userId}";
        } catch (\Throwable) {
            return "User #{$userId}";
        }
    }

    /**
     * Converts a DB status slug to a human-readable label.
     */
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
