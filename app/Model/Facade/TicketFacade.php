<?php

declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Database\DatabaseService;
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
        private readonly DatabaseService       $db,
    ) {
    }

    // ==================================================================
    //  Ticket CRUD
    // ==================================================================

    /**
     * Creates a new open ticket and notifies all support users.
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

        $this->notificationFacade->notifyRole(
            'support',
            NotificationFacade::TYPE_TICKET_CREATED,
            "New ticket #{$ticket->id} \"{$ticket->title}\" was created by {$creatorName}.",
            $ticketUrl,
        );

        // Notify admins too — they need to see all new tickets.
        $this->notificationFacade->notifyRole(
            'admin',
            NotificationFacade::TYPE_TICKET_CREATED,
            "New ticket #{$ticket->id} \"{$ticket->title}\" was created by {$creatorName}.",
            $ticketUrl,
        );

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
     * Assigns a ticket to a support user and notifies the assignee.
     */
    public function assign(int $ticketId, int $assignedTo): void
    {
        $ticket = $this->ticketRepository->findById($ticketId);

        $this->ticketRepository->update($ticketId, [
            'assigned_to' => $assignedTo,
            'updated_at'  => new \DateTimeImmutable(),
        ]);

        $title = $ticket ? "\"{$ticket->title}\"" : "#{$ticketId}";

        $this->notificationFacade->notify(
            $assignedTo,
            NotificationFacade::TYPE_TICKET_ASSIGNED,
            "Ticket #{$ticketId} {$title} has been assigned to you.",
            '/ticket/detail/' . $ticketId,
        );
    }

    /**
     * Changes ticket status, enforcing valid transitions.
     *
     * Rules:
     *   - Closed tickets can only be reopened by admins.
     *   - Notifies the ticket creator on every status change.
     *
     * @throws \RuntimeException on invalid transition or unknown ticket
     */
    public function changeStatus(int $id, string $newStatus, bool $isAdmin): void
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

        $this->notificationFacade->notify(
            (int) $ticket->created_by,
            NotificationFacade::TYPE_TICKET_STATUS_CHANGED,
            "Your ticket #{$id} \"{$ticket->title}\" status has changed to {$label}.",
            '/ticket/detail/' . $id,
        );
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

        // Notify assigned support user.
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
