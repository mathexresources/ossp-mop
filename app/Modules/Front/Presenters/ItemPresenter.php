<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use App\Core\SecuredPresenter;
use App\Model\Database\RowType;
use App\Model\Facade\ItemFacade;
use App\Model\Repository\ItemRepository;
use App\Model\Repository\ItemTypeRepository;
use App\Model\Repository\LocationRepository;
use App\Model\Repository\ServiceHistoryRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;

final class ItemPresenter extends SecuredPresenter
{
    protected ?string $requiredRole = 'employee';

    private ?ActiveRow $targetItem = null;

    private ItemFacade               $itemFacade;
    private ItemRepository           $itemRepository;
    private ItemTypeRepository       $itemTypeRepository;
    private LocationRepository       $locationRepository;
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
    //  LIST  (read-only)
    // ==================================================================

    public function renderDefault(): void
    {
        $typeIdRaw     = $this->getParameter('typeId');
        $locationIdRaw = $this->getParameter('locationId');
        $searchRaw     = $this->getParameter('search');

        $typeId     = is_numeric($typeIdRaw) ? (int) $typeIdRaw : 0;
        $locationId = is_numeric($locationIdRaw) ? (int) $locationIdRaw : 0;
        $search     = is_string($searchRaw) ? trim($searchRaw) : '';

        $this->template->title      = 'Browse Items';
        $this->template->items      = $this->itemRepository->findAllFiltered($typeId, $locationId, $search);
        $this->template->itemTypes  = $this->itemTypeRepository->fetchPairsForSelect();
        $this->template->locations  = $this->locationRepository->fetchPairsForSelect();
        $this->template->filters    = [
            'typeId'     => $typeId,
            'locationId' => $locationId,
            'search'     => $search,
        ];
    }

    // ==================================================================
    //  DETAIL  (+ service history; add form for support/admin)
    // ==================================================================

    public function actionDetail(int $id): void
    {
        $this->targetItem = $this->requireItem($id);
    }

    public function renderDetail(int $id): void
    {
        $item = $this->targetItem ?? throw new \LogicException('Target item not loaded.');

        $itemName   = RowType::string($item->name);
        $itemTypeId = RowType::int($item->item_type_id);
        $locationId = RowType::int($item->location_id);
        $itemId     = RowType::int($item->id);

        $this->template->title        = "Item — {$itemName}";
        $this->template->targetItem   = $item;
        $this->template->itemType     = $this->itemTypeRepository->findById($itemTypeId);
        $this->template->location     = $this->locationRepository->findById($locationId);
        $this->template->history      = $this->serviceHistoryRepository->findByItem($itemId)->fetchAll();
        $this->template->canAddRecord = $this->roleHelper->isSupport();
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

    public function addServiceRecordFormSucceeded(Form $form, mixed $values): void
    {
        $item   = $this->targetItem ?? throw new \LogicException('Target item not loaded.');
        $itemId = RowType::int($item->id);

        if (!$this->roleHelper->isSupport()) {
            $this->flashMessage('You do not have permission to add service records.', 'danger');
            $this->redirect('detail', $itemId);
        }

        try {
            $data        = $form->getValues(true);
            $description = RowType::string($data['description']);
            $this->itemFacade->addServiceRecord($itemId, $description, (int) $this->getUser()->getId());
            $this->flashMessage('Service record added.', 'success');
            $this->redirect('detail', $itemId);
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to add service record: ' . $e->getMessage(), 'danger');
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
