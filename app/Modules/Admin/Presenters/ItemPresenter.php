<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Model\Facade\ItemFacade;
use App\Model\Repository\ItemRepository;
use App\Model\Repository\ItemTypeRepository;
use App\Model\Repository\LocationRepository;
use App\Model\Repository\ServiceHistoryRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Paginator;

/**
 * Admin CRUD for items.
 *
 * Item types and locations each have their own presenter
 * (ItemTypePresenter, LocationPresenter).
 *
 * Actions:
 *   default — paginated, filterable item list
 *   create  — add a new item
 *   edit    — modify an existing item
 *   detail  — view item info + service history + add service record
 *   delete  — confirm and hard-delete (blocked when tickets exist)
 */
final class ItemPresenter extends BasePresenter
{
    private const ITEMS_PER_PAGE = 20;

    private ?ActiveRow $targetItem = null;

    private ItemFacade             $itemFacade;
    private ItemRepository         $itemRepository;
    private ItemTypeRepository     $itemTypeRepository;
    private LocationRepository     $locationRepository;
    private ServiceHistoryRepository $serviceHistoryRepository;

    public function injectItemFacade(ItemFacade $itemFacade): void
    {
        $this->itemFacade = $itemFacade;
    }

    public function injectItemRepository(ItemRepository $itemRepository): void
    {
        $this->itemRepository = $itemRepository;
    }

    public function injectItemTypeRepository(ItemTypeRepository $itemTypeRepository): void
    {
        $this->itemTypeRepository = $itemTypeRepository;
    }

    public function injectLocationRepository(LocationRepository $locationRepository): void
    {
        $this->locationRepository = $locationRepository;
    }

    public function injectServiceHistoryRepository(ServiceHistoryRepository $serviceHistoryRepository): void
    {
        $this->serviceHistoryRepository = $serviceHistoryRepository;
    }

    // ==================================================================
    //  LIST
    // ==================================================================

    public function renderDefault(int $page = 1): void
    {
        $typeId     = (int) ($this->getParameter('typeId')     ?? 0);
        $locationId = (int) ($this->getParameter('locationId') ?? 0);
        $search     = trim((string) ($this->getParameter('search') ?? ''));

        $total = $this->itemRepository->findAllFiltered($typeId, $locationId, $search)->count('*');

        $paginator = new Paginator();
        $paginator->setItemCount($total);
        $paginator->setItemsPerPage(self::ITEMS_PER_PAGE);
        $paginator->setPage($page);

        $items = $this->itemRepository->findAllFiltered($typeId, $locationId, $search)
            ->limit($paginator->getLength(), $paginator->getOffset());

        $this->template->title      = 'Items';
        $this->template->items      = $items;
        $this->template->paginator  = $paginator;
        $this->template->itemTypes  = $this->itemTypeRepository->fetchPairsForSelect();
        $this->template->locations  = $this->locationRepository->fetchPairsForSelect();
        $this->template->filters    = [
            'typeId'     => $typeId,
            'locationId' => $locationId,
            'search'     => $search,
        ];
    }

    // ==================================================================
    //  CREATE
    // ==================================================================

    public function renderCreate(): void
    {
        $this->template->title     = 'Create Item';
        $this->template->itemTypes = $this->itemTypeRepository->fetchPairsForSelect();
        $this->template->locations = $this->locationRepository->fetchPairsForSelect();
    }

    protected function createComponentCreateForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('name', 'Name')
            ->setRequired('Name is required.')
            ->setMaxLength(120);

        $form->addSelect('item_type_id', 'Item Type', $this->itemTypeRepository->fetchPairsForSelect())
            ->setPrompt('— select type —')
            ->setRequired('Item type is required.');

        $form->addSelect('location_id', 'Location', $this->locationRepository->fetchPairsForSelect())
            ->setPrompt('— select location —')
            ->setRequired('Location is required.');

        $form->addTextArea('description', 'Description')
            ->setHtmlAttribute('rows', 4)
            ->setMaxLength(5000);

        $form->addSubmit('submit', 'Create Item')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'createFormSucceeded'];

        return $form;
    }

    public function createFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $item = $this->itemFacade->createItem((array) $values);
            $this->flashMessage("Item \"{$item->name}\" created successfully.", 'success');
            $this->redirect('detail', $item->id);
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to create item: ' . $e->getMessage(), 'danger');
        }
    }

    // ==================================================================
    //  EDIT
    // ==================================================================

    public function actionEdit(int $id): void
    {
        $this->targetItem = $this->requireItem($id);
    }

    public function renderEdit(int $id): void
    {
        $item = $this->targetItem;

        $this->template->title      = "Edit Item — {$item->name}";
        $this->template->targetItem = $item;
        $this->template->itemTypes  = $this->itemTypeRepository->fetchPairsForSelect();
        $this->template->locations  = $this->locationRepository->fetchPairsForSelect();

        $this['editForm']->setDefaults([
            'name'         => $item->name,
            'item_type_id' => $item->item_type_id,
            'location_id'  => $item->location_id,
            'description'  => $item->description,
        ]);
    }

    protected function createComponentEditForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('name', 'Name')
            ->setRequired('Name is required.')
            ->setMaxLength(120);

        $form->addSelect('item_type_id', 'Item Type', $this->itemTypeRepository->fetchPairsForSelect())
            ->setPrompt('— select type —')
            ->setRequired('Item type is required.');

        $form->addSelect('location_id', 'Location', $this->locationRepository->fetchPairsForSelect())
            ->setPrompt('— select location —')
            ->setRequired('Location is required.');

        $form->addTextArea('description', 'Description')
            ->setHtmlAttribute('rows', 4)
            ->setMaxLength(5000);

        $form->addSubmit('submit', 'Save Changes')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'editFormSucceeded'];

        return $form;
    }

    public function editFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->itemFacade->updateItem($this->targetItem->id, (array) $values);
            $this->flashMessage('Item updated successfully.', 'success');
            $this->redirect('detail', $this->targetItem->id);
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to update item: ' . $e->getMessage(), 'danger');
        }
    }

    // ==================================================================
    //  DETAIL  (+ service history + add record form)
    // ==================================================================

    public function actionDetail(int $id): void
    {
        $this->targetItem = $this->requireItem($id);
    }

    public function renderDetail(int $id): void
    {
        $item = $this->targetItem;

        $this->template->title       = "Item Detail — {$item->name}";
        $this->template->targetItem  = $item;
        $this->template->itemType    = $this->itemTypeRepository->findById($item->item_type_id);
        $this->template->location    = $this->locationRepository->findById($item->location_id);
        $this->template->history     = $this->serviceHistoryRepository->findByItem($item->id)->fetchAll();
    }

    protected function createComponentAddServiceRecordForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addTextArea('description', 'Service Record')
            ->setRequired('Description is required.')
            ->setHtmlAttribute('rows', 4)
            ->setHtmlAttribute('placeholder', 'Describe the service action performed…')
            ->setMaxLength(5000);

        $form->addSubmit('submit', 'Add Record')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'addServiceRecordFormSucceeded'];

        return $form;
    }

    public function addServiceRecordFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->itemFacade->addServiceRecord(
                $this->targetItem->id,
                $values->description,
                (int) $this->getUser()->getId(),
            );
            $this->flashMessage('Service record added.', 'success');
            $this->redirect('detail', $this->targetItem->id);
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to add service record: ' . $e->getMessage(), 'danger');
        }
    }

    // ==================================================================
    //  DELETE
    // ==================================================================

    public function actionDelete(int $id): void
    {
        $this->targetItem = $this->requireItem($id);
    }

    public function renderDelete(int $id): void
    {
        $this->template->title      = 'Delete Item';
        $this->template->targetItem = $this->targetItem;
        $this->template->hasTickets = $this->itemRepository->hasTickets($id);
    }

    protected function createComponentDeleteForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addSubmit('delete', 'Yes, Delete This Item')
            ->setHtmlAttribute('class', 'btn btn-danger');

        $form->onSuccess[] = [$this, 'deleteFormSucceeded'];

        return $form;
    }

    public function deleteFormSucceeded(Form $form, \stdClass $values): void
    {
        $name = $this->targetItem->name;

        try {
            $this->itemFacade->deleteItem($this->targetItem->id);
            $this->flashMessage("Item \"{$name}\" has been deleted.", 'success');
            $this->redirect('default');
        } catch (\RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
            $this->redirect('delete', $this->targetItem->id);
        }
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    private function requireItem(int $id): ActiveRow
    {
        $item = $this->itemRepository->findById($id);

        if ($item === null) {
            $this->error('Item not found.', 404);
        }

        return $item;
    }
}
