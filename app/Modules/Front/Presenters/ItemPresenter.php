<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use App\Core\SecuredPresenter;
use App\Model\Facade\ItemFacade;
use App\Model\Repository\ItemRepository;
use App\Model\Repository\ItemTypeRepository;
use App\Model\Repository\LocationRepository;
use App\Model\Repository\ServiceHistoryRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;

/**
 * Public item browser — read-only for employees, with service record
 * creation for support and admin.
 *
 * Minimum role: employee (all approved users can browse).
 *
 * Actions:
 *   default — filterable item list
 *   detail  — item info + service history (support/admin may add records)
 */
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
        $typeId     = (int) ($this->getParameter('typeId')     ?? 0);
        $locationId = (int) ($this->getParameter('locationId') ?? 0);
        $search     = trim((string) ($this->getParameter('search') ?? ''));

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
        $item = $this->targetItem;

        $this->template->title      = "Item — {$item->name}";
        $this->template->targetItem = $item;
        $this->template->itemType   = $this->itemTypeRepository->findById($item->item_type_id);
        $this->template->location   = $this->locationRepository->findById($item->location_id);
        $this->template->history    = $this->serviceHistoryRepository->findByItem($item->id)->fetchAll();
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

    public function addServiceRecordFormSucceeded(Form $form, \stdClass $values): void
    {
        // Gate: only support+ may add service records.
        if (!$this->roleHelper->isSupport()) {
            $this->flashMessage('You do not have permission to add service records.', 'danger');
            $this->redirect('detail', $this->targetItem->id);
        }

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
