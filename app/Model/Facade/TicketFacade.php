<?php

declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Database\DatabaseService;
use App\Model\Database\RowType;
use App\Model\Mail\MailService;
use App\Model\Repository\TicketImageRepository;
use App\Model\Repository\TicketRepository;
use App\Model\Repository\UserRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\FileUpload;

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

    /**
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
            ? RowType::string($creator->first_name) . ' ' . RowType::string($creator->last_name)
            : "User #{$createdBy}";

        $ticketId = RowType::int($ticket->id);
        $ticketTitle = RowType::string($ticket->title);
        $ticketUrl = '/ticket/detail/' . $ticketId;

        $this->notificationFacade->notifyRole(
            'support',
            NotificationFacade::TYPE_TICKET_CREATED,
            "New ticket #{$ticketId} \"{$ticketTitle}\" was created by {$creatorName}.",
            $ticketUrl,
        );

        $this->notificationFacade->notifyRole(
            'admin',
            NotificationFacade::TYPE_TICKET_CREATED,
            "New ticket #{$ticketId} \"{$ticketTitle}\" was created by {$creatorName}.",
            $ticketUrl,
        );

        $supportUsers = $this->userRepository->findByRole('support')
            ->where('status', 'approved')
            ->fetchAll();

        $this->mailService->sendTicketCreated($ticket, $supportUsers);

        return $ticket;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->ticketRepository->update($id, [
            'title'       => trim(RowType::string($data['title'])),
            'description' => trim(RowType::string($data['description'])),
            'updated_at'  => new \DateTimeImmutable(),
        ]);
    }

    public function assign(int $ticketId, int $assignedTo): void
    {
        $ticket = $this->ticketRepository->findById($ticketId);

        $this->ticketRepository->update($ticketId, [
            'assigned_to' => $assignedTo,
            'updated_at'  => new \DateTimeImmutable(),
        ]);

        $title = $ticket ? '"' . RowType::string($ticket->title) . '"' : "#{$ticketId}";

        $this->notificationFacade->notify(
            $assignedTo,
            NotificationFacade::TYPE_TICKET_ASSIGNED,
            "Ticket #{$ticketId} {$title} has been assigned to you.",
            '/ticket/detail/' . $ticketId,
        );

        if ($ticket !== null) {
            $assignee = $this->userRepository->findById($assignedTo);
            if ($assignee !== null) {
                $this->mailService->sendTicketAssigned($ticket, $assignee);
            }
        }
    }

    /** @throws \RuntimeException on invalid transition or unknown ticket */
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

        if (RowType::string($ticket->status) === 'closed' && !$isAdmin) {
            throw new \RuntimeException('Only administrators can reopen a closed ticket.');
        }

        $oldStatus = RowType::string($ticket->status);

        $this->ticketRepository->update($id, [
            'status'     => $newStatus,
            'updated_at' => new \DateTimeImmutable(),
        ]);

        $label = match ($newStatus) {
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'closed'      => 'Closed',
        };

        $ticketTitle = RowType::string($ticket->title);
        $createdBy = RowType::int($ticket->created_by);

        $this->notificationFacade->notify(
            $createdBy,
            NotificationFacade::TYPE_TICKET_STATUS_CHANGED,
            "Your ticket #{$id} \"{$ticketTitle}\" status has changed to {$label}.",
            '/ticket/detail/' . $id,
        );

        $creator = $this->userRepository->findById($createdBy);
        if ($creator !== null) {
            $updatedTicket = $this->ticketRepository->findById($id);
            $this->mailService->sendTicketStatusChanged(
                $updatedTicket ?? $ticket,
                $creator,
                $oldStatus,
                $changedBy,
            );
        }
    }

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

    /** @throws \RuntimeException on validation failure or write error */
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

        $ticket = $this->ticketRepository->findById($ticketId);
        if ($ticket !== null) {
            $assignedId = RowType::nullableInt($ticket->assigned_to);
            if ($assignedId !== null) {
                $this->notificationFacade->notify(
                    $assignedId,
                    NotificationFacade::TYPE_IMAGE_ADDED,
                    "A new image was added to ticket #{$ticketId} \"" . RowType::string($ticket->title) . '".',
                    '/ticket/detail/' . $ticketId,
                );
            }
        }

        return $image;
    }

    /** @throws \RuntimeException when the image is not found */
    public function deleteImage(int $imageId): void
    {
        $image = $this->ticketImageRepository->findById($imageId);
        if ($image === null) {
            throw new \RuntimeException('Image not found.');
        }

        $this->ticketImageRepository->softDelete($imageId);
    }

    private function uploadDir(int $ticketId): string
    {
        return dirname(__DIR__, 3) . '/www/uploads/tickets/' . $ticketId;
    }
}
