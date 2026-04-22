<?php

declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Database\RowType;
use App\Model\Mail\MailService;
use App\Model\Repository\ItemRepository;
use App\Model\Repository\ItemTypeRepository;
use App\Model\Repository\LocationRepository;
use App\Model\Repository\ServiceHistoryRepository;
use App\Model\Repository\TicketRepository;
use App\Model\Repository\UserRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\FileUpload;

/**
 * Business logic for items, item types, locations, and service history.
 *
 * All mutating methods validate business rules and throw \RuntimeException
 * on constraint violations so callers can convert them to flash messages.
 */
final class ItemFacade
{
    public function __construct(
        private readonly ItemRepository           $itemRepository,
        private readonly ItemTypeRepository       $itemTypeRepository,
        private readonly LocationRepository       $locationRepository,
        private readonly ServiceHistoryRepository $serviceHistoryRepository,
        private readonly TicketRepository         $ticketRepository,
        private readonly UserRepository           $userRepository,
        private readonly NotificationFacade       $notificationFacade,
        private readonly MailService              $mailService,
    ) {
    }

    // ==================================================================
    //  Item Types
    // ==================================================================

    public function createItemType(string $name): ActiveRow
    {
        $name = trim($name);

        if ($this->itemTypeRepository->nameExistsExcept($name)) {
            throw new \RuntimeException("Item type \"{$name}\" already exists.");
        }

        return $this->itemTypeRepository->insert(['name' => $name]);
    }

    public function updateItemType(int $id, string $name): void
    {
        $name = trim($name);

        if ($this->itemTypeRepository->nameExistsExcept($name, $id)) {
            throw new \RuntimeException("Item type \"{$name}\" already exists.");
        }

        $this->itemTypeRepository->update($id, ['name' => $name]);
    }

    /**
     * Uploads (or replaces) the blueprint image for an item type.
     *
     * Accepted formats: jpg / jpeg / png, max 10 MB.
     * Saved to:  www/uploads/blueprints/{id}/blueprint.{ext}
     *
     * @throws \RuntimeException on validation failure
     */
    public function updateItemTypeBlueprint(int $id, FileUpload $file): void
    {
        if (!$file->isOk() || $file->getSize() === 0) {
            throw new \RuntimeException('No valid file was uploaded.');
        }

        $ext = strtolower(pathinfo($file->getUntrustedName(), PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            throw new \RuntimeException('Blueprint must be a JPG or PNG image.');
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \RuntimeException('Blueprint must be smaller than 10 MB.');
        }

        $dir = $this->blueprintDir($id);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $safeExt  = ($ext === 'jpeg') ? 'jpg' : $ext;
        $filename = 'blueprint.' . $safeExt;

        $file->move($dir . $filename);

        $path = 'uploads/blueprints/' . $id . '/' . $filename;
        $this->itemTypeRepository->update($id, ['blueprint_path' => $path]);
    }

    /**
     * Removes the blueprint image for an item type (deletes the file and clears the DB column).
     */
    public function removeItemTypeBlueprint(int $id): void
    {
        $type = $this->itemTypeRepository->findById($id);

        if ($type === null) {
            return;
        }

        $blueprintPath = RowType::nullableString($type->blueprint_path);
        if ($blueprintPath !== null) {
            $fullPath = $this->wwwRoot() . '/' . $blueprintPath;
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $this->itemTypeRepository->update($id, ['blueprint_path' => null]);
    }

    /**
     * Deletes an item type only when no items are assigned to it.
     * Also removes any uploaded blueprint file.
     *
     * @throws \RuntimeException when items are still assigned
     */
    public function deleteItemType(int $id): void
    {
        if ($this->itemTypeRepository->hasItems($id)) {
            throw new \RuntimeException(
                'Cannot delete: one or more items are assigned to this type. '
                . 'Reassign or delete those items first.'
            );
        }

        $type = $this->itemTypeRepository->findById($id);
        if ($type !== null) {
            $blueprintPath = RowType::nullableString($type->blueprint_path);
            if ($blueprintPath !== null) {
                $fullPath = $this->wwwRoot() . '/' . $blueprintPath;
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }

        $this->itemTypeRepository->delete($id);
    }

    // ==================================================================
    //  Locations
    // ==================================================================

    public function createLocation(string $name): ActiveRow
    {
        $name = trim($name);

        if ($this->locationRepository->nameExistsExcept($name)) {
            throw new \RuntimeException("Location \"{$name}\" already exists.");
        }

        return $this->locationRepository->insert(['name' => $name]);
    }

    public function updateLocation(int $id, string $name): void
    {
        $name = trim($name);

        if ($this->locationRepository->nameExistsExcept($name, $id)) {
            throw new \RuntimeException("Location \"{$name}\" already exists.");
        }

        $this->locationRepository->update($id, ['name' => $name]);
    }

    /**
     * Deletes a location only when no items are assigned to it.
     *
     * @throws \RuntimeException when items are still assigned
     */
    public function deleteLocation(int $id): void
    {
        if ($this->locationRepository->hasItems($id)) {
            throw new \RuntimeException(
                'Cannot delete: one or more items are assigned to this location. '
                . 'Reassign or delete those items first.'
            );
        }

        $this->locationRepository->delete($id);
    }

    // ==================================================================
    //  Items
    // ==================================================================

    /**
     * @param array{name: string, item_type_id: int|string, location_id: int|string, description?: string} $data
     */
    public function createItem(array $data): ActiveRow
    {
        return $this->itemRepository->insert([
            'name'         => trim($data['name']),
            'item_type_id' => (int) $data['item_type_id'],
            'location_id'  => (int) $data['location_id'],
            'description'  => $this->nullable($data['description'] ?? ''),
        ]);
    }

    /**
     * @param array{name: string, item_type_id: int|string, location_id: int|string, description?: string} $data
     */
    public function updateItem(int $id, array $data): void
    {
        $this->itemRepository->update($id, [
            'name'         => trim($data['name']),
            'item_type_id' => (int) $data['item_type_id'],
            'location_id'  => (int) $data['location_id'],
            'description'  => $this->nullable($data['description'] ?? ''),
        ]);
    }

    /**
     * Hard-deletes an item only when no tickets reference it.
     *
     * @throws \RuntimeException when tickets still reference the item
     */
    public function deleteItem(int $id): void
    {
        if ($this->itemRepository->hasTickets($id)) {
            throw new \RuntimeException(
                'Cannot delete: one or more tickets reference this item. '
                . 'Close or delete those tickets first.'
            );
        }

        $this->itemRepository->delete($id);
    }

    // ==================================================================
    //  Service History  (append-only)
    // ==================================================================

    /**
     * Appends a service history record to an item.
     * Notifies via in-app notification and email the creator of any
     * open/in-progress tickets for the item.
     */
    public function addServiceRecord(int $itemId, string $description, int $createdBy): ActiveRow
    {
        $record = $this->serviceHistoryRepository->addRecord($itemId, $description, $createdBy);

        // Resolve "added by" name for email template.
        $addedByUser = $this->userRepository->findById($createdBy);
        $addedByName = $addedByUser
            ? trim(RowType::string($addedByUser->first_name) . ' ' . RowType::string($addedByUser->last_name))
            : "User #{$createdBy}";

        foreach ($this->ticketRepository->findActiveByItem($itemId) as $ticket) {
            $creatorId = RowType::int($ticket->created_by);
            $ticketId = RowType::int($ticket->id);
            $ticketTitle = RowType::string($ticket->title);

            // Don't notify the person who added the record.
            if ($creatorId === $createdBy) {
                continue;
            }

            // In-app notification.
            $this->notificationFacade->notify(
                $creatorId,
                NotificationFacade::TYPE_SERVICE_HISTORY_ADDED,
                "A new service record was added for the item associated with your ticket #{$ticketId} \"{$ticketTitle}\".",
                '/ticket/detail/' . $ticketId,
            );

            // Email the ticket creator.
            $creator = $this->userRepository->findById($creatorId);
            if ($creator !== null) {
                $this->mailService->sendServiceHistoryAdded(
                    $ticket,
                    $creator,
                    $description,
                    $addedByName,
                );
            }
        }

        return $record;
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    private function nullable(mixed $value): mixed
    {
        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value ?: null;
    }

    /** Absolute path to the www root (one level above this file's app/). */
    private function wwwRoot(): string
    {
        return dirname(__DIR__, 3) . '/www';
    }

    /** Absolute path to the blueprint upload directory for an item type. */
    private function blueprintDir(int $itemTypeId): string
    {
        return $this->wwwRoot() . '/uploads/blueprints/' . $itemTypeId . '/';
    }
}
