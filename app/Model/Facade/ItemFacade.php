<?php

declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Repository\ItemRepository;
use App\Model\Repository\ItemTypeRepository;
use App\Model\Repository\LocationRepository;
use App\Model\Repository\ServiceHistoryRepository;
use Nette\Database\Table\ActiveRow;

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
     * Deletes an item type only when no items are assigned to it.
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
     * The timestamp is always set to now inside the repository.
     */
    public function addServiceRecord(int $itemId, string $description, int $createdBy): ActiveRow
    {
        return $this->serviceHistoryRepository->addRecord($itemId, $description, $createdBy);
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
}
