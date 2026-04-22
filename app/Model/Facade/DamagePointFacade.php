<?php

declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Database\RowType;
use App\Model\Mail\MailService;
use App\Model\Repository\DamagePointRepository;
use App\Model\Repository\TicketRepository;
use App\Model\Repository\UserRepository;
use Nette\Database\Table\ActiveRow;

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
     * @throws \RuntimeException On validation or permission failure
     */
    public function addPoint(
        int $ticketId,
        float $x,
        float $y,
        string $description,
        int $userId,
        bool $isAdmin,
        bool $isSupport,
    ): ActiveRow {
        $ticket = $this->ticketRepository->findById($ticketId);
        if ($ticket === null) {
            throw new \RuntimeException('Ticket not found.');
        }

        if (RowType::string($ticket->status) === 'closed') {
            throw new \RuntimeException('Cannot add damage points to a closed ticket.');
        }

        $isOwner = RowType::int($ticket->created_by) === $userId;
        $canAdd  = $isAdmin || $isSupport || ($isOwner && RowType::string($ticket->status) === 'open');

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

        $assignedId = RowType::nullableInt($ticket->assigned_to);
        if ($assignedId !== null && $assignedId !== $userId) {
            $ticketTitle = RowType::string($ticket->title);
            $this->notificationFacade->notify(
                $assignedId,
                NotificationFacade::TYPE_DAMAGE_POINT_ADDED,
                "A new damage point was added to ticket #{$ticketId} \"{$ticketTitle}\": {$description}",
                '/ticket/detail/' . $ticketId,
            );

            $assignee = $this->userRepository->findById($assignedId);
            if ($assignee !== null) {
                $this->mailService->sendDamagePointAdded($ticket, $assignee, $description);
            }
        }

        return $point;
    }

    /** @throws \RuntimeException On not-found, closed ticket, or permission failure */
    public function removePoint(int $id, int $userId, bool $isAdmin, bool $isSupport): void
    {
        $point = $this->damagePointRepository->findById($id);
        if ($point === null) {
            throw new \RuntimeException('Damage point not found.');
        }

        $ticket = $this->ticketRepository->findById(RowType::int($point->ticket_id));
        if ($ticket === null) {
            throw new \RuntimeException('Associated ticket not found.');
        }

        if (RowType::string($ticket->status) === 'closed') {
            throw new \RuntimeException('Cannot remove damage points from a closed ticket.');
        }

        $isOwner   = RowType::int($ticket->created_by) === $userId;
        $canDelete = $isAdmin || $isSupport || ($isOwner && RowType::string($ticket->status) === 'open');

        if (!$canDelete) {
            throw new \RuntimeException('You do not have permission to remove this damage point.');
        }

        $this->damagePointRepository->softDelete($id);
    }

    /** @return list<array{id:int, position_x:float, position_y:float, description:string}> */
    public function getForTicket(int $ticketId): array
    {
        $result = [];

        foreach ($this->damagePointRepository->findByTicket($ticketId)->fetchAll() as $row) {
            $result[] = [
                'id'          => RowType::int($row->id),
                'position_x'  => RowType::float($row->position_x),
                'position_y'  => RowType::float($row->position_y),
                'description' => RowType::string($row->description),
            ];
        }

        return $result;
    }
}
