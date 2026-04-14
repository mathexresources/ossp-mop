<?php

declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Mail\MailService;
use App\Model\Repository\DamagePointRepository;
use App\Model\Repository\TicketRepository;
use App\Model\Repository\UserRepository;
use Nette\Database\Table\ActiveRow;

/**
 * Business logic for ticket damage points.
 *
 * Coordinates are stored as percentages (0.0–100.0) relative to the
 * blueprint image dimensions, making them resolution-independent.
 *
 * Permission rules:
 *   - Ticket closed   → nobody can add or remove points
 *   - Ticket open     → ticket owner, support, and admin can add/remove
 *   - Ticket in_progress → support and admin only
 */
final class DamagePointFacade
{
    public function __construct(
        private readonly DamagePointRepository $damagePointRepository,
        private readonly TicketRepository      $ticketRepository,
        private readonly UserRepository        $userRepository,
        private readonly NotificationFacade    $notificationFacade,
        private readonly MailService           $mailService,
    ) {
    }

    /**
     * Adds a damage point to a ticket.
     * Notifies the assigned support user via in-app notification and email (if any).
     *
     * @param  int    $ticketId    Target ticket
     * @param  float  $x          Horizontal position (0–100 %)
     * @param  float  $y          Vertical position (0–100 %)
     * @param  string $description Short description of the damage
     * @param  int    $userId      Current user's id
     * @param  bool   $isAdmin     Whether the current user is an admin
     * @param  bool   $isSupport   Whether the current user is support or above
     * @return ActiveRow           The newly created damage point row
     * @throws \RuntimeException   On validation or permission failure
     */
    public function addPoint(
        int    $ticketId,
        float  $x,
        float  $y,
        string $description,
        int    $userId,
        bool   $isAdmin,
        bool   $isSupport,
    ): ActiveRow {
        $ticket = $this->ticketRepository->findById($ticketId);
        if ($ticket === null) {
            throw new \RuntimeException('Ticket not found.');
        }

        if ($ticket->status === 'closed') {
            throw new \RuntimeException('Cannot add damage points to a closed ticket.');
        }

        $isOwner = (int) $ticket->created_by === $userId;
        $canAdd  = $isAdmin || $isSupport || ($isOwner && $ticket->status === 'open');

        if (!$canAdd) {
            throw new \RuntimeException('You do not have permission to add damage points to this ticket.');
        }

        if ($x < 0.0 || $x > 100.0 || $y < 0.0 || $y > 100.0) {
            throw new \RuntimeException('Coordinates must be between 0.0 and 100.0.');
        }

        $description = trim($description);
        if ($description === '') {
            throw new \RuntimeException('Damage point description is required.');
        }
        if (mb_strlen($description) > 500) {
            throw new \RuntimeException('Description must not exceed 500 characters.');
        }

        $point = $this->damagePointRepository->insert([
            'ticket_id'   => $ticketId,
            'position_x'  => $x,
            'position_y'  => $y,
            'description' => $description,
        ]);

        // Notify assigned support user (skip self-notification).
        if ($ticket->assigned_to) {
            $assignedId = (int) $ticket->assigned_to;
            if ($assignedId !== $userId) {
                // In-app notification.
                $this->notificationFacade->notify(
                    $assignedId,
                    NotificationFacade::TYPE_DAMAGE_POINT_ADDED,
                    "A new damage point was added to ticket #{$ticketId} \"{$ticket->title}\": {$description}",
                    '/ticket/detail/' . $ticketId,
                );

                // Email the assigned support user.
                $assignee = $this->userRepository->findById($assignedId);
                if ($assignee !== null) {
                    $this->mailService->sendDamagePointAdded($ticket, $assignee, $description);
                }
            }
        }

        return $point;
    }

    /**
     * Removes (soft-deletes) a damage point.
     *
     * @throws \RuntimeException On not-found, closed ticket, or permission failure
     */
    public function removePoint(int $id, int $userId, bool $isAdmin, bool $isSupport): void
    {
        $point = $this->damagePointRepository->findById($id);
        if ($point === null) {
            throw new \RuntimeException('Damage point not found.');
        }

        $ticket = $this->ticketRepository->findById((int) $point->ticket_id);
        if ($ticket === null) {
            throw new \RuntimeException('Associated ticket not found.');
        }

        if ($ticket->status === 'closed') {
            throw new \RuntimeException('Cannot remove damage points from a closed ticket.');
        }

        $isOwner   = (int) $ticket->created_by === $userId;
        $canDelete = $isAdmin || $isSupport || ($isOwner && $ticket->status === 'open');

        if (!$canDelete) {
            throw new \RuntimeException('You do not have permission to remove this damage point.');
        }

        $this->damagePointRepository->softDelete($id);
    }

    /**
     * Returns all active damage points for a ticket as plain arrays.
     *
     * @return list<array{id:int, position_x:float, position_y:float, description:string}>
     */
    public function getForTicket(int $ticketId): array
    {
        $result = [];

        foreach ($this->damagePointRepository->findByTicket($ticketId)->fetchAll() as $row) {
            $result[] = [
                'id'          => (int)    $row->id,
                'position_x'  => (float)  $row->position_x,
                'position_y'  => (float)  $row->position_y,
                'description' => (string) $row->description,
            ];
        }

        return $result;
    }
}
