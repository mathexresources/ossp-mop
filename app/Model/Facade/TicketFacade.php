<?php

declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Database\DatabaseService;
use App\Model\Mail\MailService;
use App\Model\Repository\TicketImageRepository;
use App\Model\Repository\TicketRepository;
use App\Model\Repository\UserRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\FileUpload;

/**
 * Business logic for tickets and ticket images.
 *
 * Upload path (inside Docker): /var/www/html/www/uploads/tickets/{ticket_id}/
 * Web-accessible path: /uploads/tickets/{ticket_id}/{filename}
 */
final class TicketFacade
{
    public function __construct(
        private readonly TicketRepository      $ticketRepository,
        private readonly TicketImageRepository $ticketImageRepository,
        private readonly UserRepository        $userRepository,
        private readonly NotificationFacade    $notificationFacade,
        private readonly MailService           $mailService,
        private readonly DatabaseService       $db,
    ) {
    }

    // ==================================================================
    //  Ticket CRUD
    // ==================================================================

    /**
     * Creates a new open ticket, notifies all support users via in-app
     * notification and email.
     *
     * @param array{title: string, description: string, item_id: int|string} $data
     */
    public function create(array $data, int $createdBy): ActiveRow
    {
        $now = new \DateTimeImmutable();

        $ticket = $this->ticketRepository->insert([
            'title'       => trim($data['title']),
            'description' => trim($data['description']),
            'item_id'     => (int) $data['item_id'],
            'created_by'  => $createdBy,
            'status'      => 'open',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $creator = $this->userRepository->findById($createdBy);
        $creatorName = $creator
            ? "{$creator->first_name} {$creator->last_name}"
            : "User #{$createdBy}";

        $ticketUrl = '/ticket/detail/' . (int) $ticket->id;

        // In-app notifications for support and admin roles.
        $this->notificationFacade->notifyRole(
            'support',
            NotificationFacade::TYPE_TICKET_CREATED,
            "New ticket #{$ticket->id} \"{$ticket->title}\" was created by {$creatorName}.",
            $ticketUrl,
        );

        $this->notificationFacade->notifyRole(
            'admin',
            NotificationFacade::TYPE_TICKET_CREATED,
            "New ticket #{$ticket->id} \"{$ticket->title}\" was created by {$creatorName}.",
            $ticketUrl,
        );

        // Email all approved support users.
        $supportUsers = $this->userRepository->findByRole('support')
            ->where('status', 'approved')
            ->fetchAll();

        $this->mailService->sendTicketCreated($ticket, $supportUsers);

        return $ticket;
    }

    /**
     * Updates title and description of a ticket.
     */
    public function update(int $id, array $data): void
    {
        $this->ticketRepository->update($id, [
            'title'       => trim($data['title']),
            'description' => trim($data['description']),
            'updated_at'  => new \DateTimeImmutable(),
        ]);
    }

    /**
     * Assigns a ticket to a support user; notifies the assignee via in-app
     * notification and email.
     */
    public function assign(int $ticketId, int $assignedTo): void
    {
        $ticket = $this->ticketRepository->findById($ticketId);

        $this->ticketRepository->update($ticketId, [
            'assigned_to' => $assignedTo,
            'updated_at'  => new \DateTimeImmutable(),
        ]);

        $title = $ticket ? "\"{$ticket->title}\"" : "#{$ticketId}";

        // In-app notification.
        $this->notificationFacade->notify(
            $assignedTo,
            NotificationFacade::TYPE_TICKET_ASSIGNED,
            "Ticket #{$ticketId} {$title} has been assigned to you.",
            '/ticket/detail/' . $ticketId,
        );

        // Email the assignee.
        if ($ticket !== null) {
            $assignee = $this->userRepository->findById($assignedTo);
            if ($assignee !== null) {
                $this->mailService->sendTicketAssigned($ticket, $assignee);
            }
        }
    }

    /**
     * Changes ticket status, enforcing valid transitions.
     *
     * Rules:
     *   - Closed tickets can only be reopened by admins.
     *   - Notifies the ticket creator on every status change (in-app + email).
     *
     * @param int  $changedBy  User ID of the person making the change (0 = unknown)
     * @throws \RuntimeException on invalid transition or unknown ticket
     */
    public function changeStatus(int $id, string $newStatus, bool $isAdmin, int $changedBy = 0): void
    {
        $ticket = $this->ticketRepository->findById($id);
        if ($ticket === null) {
            throw new \RuntimeException('Ticket not found.');
        }

        $valid = ['open', 'in_progress', 'closed'];
        if (!in_array($newStatus, $valid, true)) {
            throw new \RuntimeException('Invalid status value.');
        }

        if ($ticket->status === 'closed' && !$isAdmin) {
            throw new \RuntimeException('Only administrators can reopen a closed ticket.');
        }

        $oldStatus = (string) $ticket->status;

        $this->ticketRepository->update($id, [
            'status'     => $newStatus,
            'updated_at' => new \DateTimeImmutable(),
        ]);

        $label = match ($newStatus) {
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'closed'      => 'Closed',
            default       => $newStatus,
        };

        // In-app notification to the ticket creator.
        $this->notificationFacade->notify(
            (int) $ticket->created_by,
            NotificationFacade::TYPE_TICKET_STATUS_CHANGED,
            "Your ticket #{$id} \"{$ticket->title}\" status has changed to {$label}.",
            '/ticket/detail/' . $id,
        );

        // Email the ticket creator.
        $creator = $this->userRepository->findById((int) $ticket->created_by);
        if ($creator !== null) {
            // Re-fetch ticket with new status for the email template.
            $updatedTicket = $this->ticketRepository->findById($id);
            $this->mailService->sendTicketStatusChanged(
                $updatedTicket ?? $ticket,
                $creator,
                $oldStatus,
                $changedBy,
            );
        }
    }

    /**
     * Soft-deletes a ticket and cascades to its images and damage points.
     */
    public function softDelete(int $id): void
    {
        $now = new \DateTimeImmutable();

        $this->ticketRepository->softDelete($id);

        $this->db->table('ticket_images')
            ->where('ticket_id', $id)
            ->where('deleted_at IS NULL')
            ->update(['deleted_at' => $now]);

        $this->db->table('ticket_damage_points')
            ->where('ticket_id', $id)
            ->where('deleted_at IS NULL')
            ->update(['deleted_at' => $now]);
    }

    // ==================================================================
    //  Image management
    // ==================================================================

    /**
     * Validates and saves a single uploaded image for a ticket.
     * Notifies the assigned support user (if any).
     *
     * Allowed types: jpg, jpeg, png  /  Max size: 5 MB
     *
     * @throws \RuntimeException on validation failure or write error
     */
    public function addImage(int $ticketId, FileUpload $file): ActiveRow
    {
        if (!$file->isOk()) {
            throw new \RuntimeException('File upload failed or no file provided.');
        }

        $ext = strtolower(pathinfo($file->getUntrustedName(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            throw new \RuntimeException(
                "Invalid file type \"{$ext}\": only JPG and PNG are allowed."
            );
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            $mb = round($file->getSize() / 1024 / 1024, 1);
            throw new \RuntimeException(
                "File \"{$file->getUntrustedName()}\" is {$mb} MB — maximum is 5 MB."
            );
        }

        $dir = $this->uploadDir($ticketId);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create upload directory.");
        }

        $filename = uniqid('img_', true) . '.' . $ext;
        $file->move($dir . '/' . $filename);

        $image = $this->ticketImageRepository->insert([
            'ticket_id' => $ticketId,
            'path'      => 'uploads/tickets/' . $ticketId . '/' . $filename,
        ]);

        // Notify assigned support user (in-app only — no email for image uploads).
        $ticket = $this->ticketRepository->findById($ticketId);
        if ($ticket && $ticket->assigned_to) {
            $this->notificationFacade->notify(
                (int) $ticket->assigned_to,
                NotificationFacade::TYPE_IMAGE_ADDED,
                "A new image was added to ticket #{$ticketId} \"{$ticket->title}\".",
                '/ticket/detail/' . $ticketId,
            );
        }

        return $image;
    }

    /**
     * Soft-deletes a ticket image record.
     *
     * @throws \RuntimeException when the image is not found
     */
    public function deleteImage(int $imageId): void
    {
        $image = $this->ticketImageRepository->findById($imageId);
        if ($image === null) {
            throw new \RuntimeException('Image not found.');
        }

        $this->ticketImageRepository->softDelete($imageId);
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    /**
     * Absolute path to the upload directory for a ticket (no trailing slash).
     *
     * __DIR__ = …/app/Model/Facade  →  dirname 3 levels up = project root
     */
    private function uploadDir(int $ticketId): string
    {
        return dirname(__DIR__, 3) . '/www/uploads/tickets/' . $ticketId;
    }
}
