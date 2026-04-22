<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use App\Core\SecuredPresenter;
use App\Model\Database\RowType;
use App\Model\Facade\DamagePointFacade;
use App\Model\Facade\ItemFacade;
use App\Model\Facade\TicketFacade;
use App\Model\Repository\ItemRepository;
use App\Model\Repository\ItemTypeRepository;
use App\Model\Repository\TicketImageRepository;
use App\Model\Repository\TicketRepository;
use App\Model\Repository\UserRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Http\FileUpload;
use Nette\Utils\Paginator;

final class TicketPresenter extends SecuredPresenter
{
    protected ?string $requiredRole = null;

    private const TICKETS_PER_PAGE = 20;

    private ?ActiveRow $targetTicket = null;

    private TicketFacade          $ticketFacade;
    private ItemFacade            $itemFacade;
    private DamagePointFacade     $damagePointFacade;
    private TicketRepository      $ticketRepository;
    private TicketImageRepository $ticketImageRepository;
    private ItemRepository        $itemRepository;
    private ItemTypeRepository    $itemTypeRepository;
    private UserRepository        $userRepository;

    public function injectTicketFacade(TicketFacade $ticketFacade): void
    {
        $this->ticketFacade = $ticketFacade;
    }

    public function injectItemFacade(ItemFacade $itemFacade): void
    {
        $this->itemFacade = $itemFacade;
    }

    public function injectDamagePointFacade(DamagePointFacade $damagePointFacade): void
    {
        $this->damagePointFacade = $damagePointFacade;
    }

    public function injectTicketRepository(TicketRepository $ticketRepository): void
    {
        $this->ticketRepository = $ticketRepository;
    }

    public function injectTicketImageRepository(TicketImageRepository $ticketImageRepository): void
    {
        $this->ticketImageRepository = $ticketImageRepository;
    }

    public function injectItemRepository(ItemRepository $itemRepository): void
    {
        $this->itemRepository = $itemRepository;
    }

    public function injectItemTypeRepository(ItemTypeRepository $itemTypeRepository): void
    {
        $this->itemTypeRepository = $itemTypeRepository;
    }

    public function injectUserRepository(UserRepository $userRepository): void
    {
        $this->userRepository = $userRepository;
    }

    // ==================================================================
    //  LIST
    // ==================================================================

    public function renderDefault(int $page = 1): void
    {
        $statusRaw = $this->getParameter('status');
        $status = is_string($statusRaw) ? trim($statusRaw) : '';

        $itemIdRaw = $this->getParameter('itemId');
        $itemId = is_int($itemIdRaw) ? $itemIdRaw : (is_string($itemIdRaw) && is_numeric($itemIdRaw) ? (int) $itemIdRaw : 0);

        $searchRaw = $this->getParameter('search');
        $search = is_string($searchRaw) ? trim($searchRaw) : '';

        $total = $this->ticketRepository->findAllFiltered($status, $itemId, $search)->count('*');

        $paginator = new Paginator();
        $paginator->setItemCount($total);
        $paginator->setItemsPerPage(self::TICKETS_PER_PAGE);
        $paginator->setPage($page);

        $tickets = $this->ticketRepository->findAllFiltered($status, $itemId, $search)
            ->limit($paginator->getLength(), $paginator->getOffset());

        $this->template->title     = 'Tickets';
        $this->template->tickets   = $tickets;
        $this->template->paginator = $paginator;
        $this->template->items     = $this->itemRepository->findAll()->fetchPairs('id', 'name');
        $this->template->filters   = [
            'status' => $status,
            'itemId' => $itemId,
            'search' => $search,
        ];
        $this->template->canCreate = $this->getUser()->isAllowed('ticket', 'create');
    }

    // ==================================================================
    //  CREATE
    // ==================================================================

    public function actionCreate(): void
    {
        if (!$this->getUser()->isAllowed('ticket', 'create')) {
            $this->flashMessage('You do not have permission to create tickets.', 'danger');
            $this->redirect('default');
        }
    }

    public function renderCreate(): void
    {
        $this->template->title = 'Create Ticket';
        $this->template->items = $this->itemRepository->findAll()->order('name ASC')->fetchPairs('id', 'name');
    }

    protected function createComponentCreateForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('title', 'Title')
            ->setRequired('Title is required.')
            ->setMaxLength(200);

        $form->addTextArea('description', 'Description')
            ->setRequired('Description is required.')
            ->setHtmlAttribute('rows', 5)
            ->setMaxLength(10000);

        $items = $this->itemRepository->findAll()->order('name ASC')->fetchPairs('id', 'name');

        $form->addSelect('item_id', 'Item', $items)
            ->setPrompt('— select item —')
            ->setRequired('Please select an item.');

        $form->addMultiUpload('images', 'Images (JPG / PNG, max 5 MB each)')
            ->setHtmlAttribute('accept', 'image/jpeg,image/png');

        $form->addSubmit('submit', 'Create Ticket')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'createFormSucceeded'];

        return $form;
    }

    public function createFormSucceeded(Form $form, mixed $values): void
    {
        if (!$this->getUser()->isAllowed('ticket', 'create')) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('default');
        }

        try {
            $data = $form->getValues(true);
            $ticket = $this->ticketFacade->create(
                [
                    'title'       => RowType::string($data['title']),
                    'description' => RowType::string($data['description']),
                    'item_id'     => RowType::intValue($data['item_id']),
                ],
                (int) $this->getUser()->getId(),
            );

            $uploadErrors = [];
            $imagesRaw = $data['images'] ?? null;
            if (is_iterable($imagesRaw)) {
                foreach ($imagesRaw as $file) {
                    if (!$file instanceof FileUpload || !$file->isOk() || $file->getSize() === 0) {
                        continue;
                    }
                    try {
                        $this->ticketFacade->addImage(RowType::int($ticket->id), $file);
                    } catch (\RuntimeException $e) {
                        $uploadErrors[] = $e->getMessage();
                    }
                }
            }

            foreach ($uploadErrors as $err) {
                $this->flashMessage($err, 'warning');
            }

            $ticketId = RowType::int($ticket->id);
            $this->flashMessage("Ticket #{$ticketId} created successfully.", 'success');
            $this->redirect('detail', $ticketId);
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to create ticket: ' . $e->getMessage(), 'danger');
        }
    }

    // ==================================================================
    //  DETAIL
    // ==================================================================

    public function actionDetail(int $id): void
    {
        $this->targetTicket = $this->requireTicket($id);
    }

    public function renderDetail(int $id): void
    {
        $ticket  = $this->targetTicket ?? throw new \LogicException('Target ticket not loaded.');
        $userId  = (int) $this->getUser()->getId();
        $isOwner = RowType::int($ticket->created_by) === $userId;

        $item     = $this->itemRepository->findById(RowType::int($ticket->item_id));
        $creator  = $this->userRepository->findById(RowType::int($ticket->created_by));
        $assignedId = RowType::nullableInt($ticket->assigned_to);
        $assignee = $assignedId !== null ? $this->userRepository->findById($assignedId) : null;
        $ticketId = RowType::int($ticket->id);
        $images   = $this->ticketImageRepository->findByTicket($ticketId)->fetchAll();

        $blueprintPath = null;
        if ($item !== null) {
            $itemType = $this->itemTypeRepository->findById(RowType::int($item->item_type_id));
            if ($itemType !== null) {
                $blueprintPath = RowType::nullableString($itemType->blueprint_path);
            }
        }

        $damagePoints = $this->damagePointFacade->getForTicket($ticketId);

        $ticketStatus = RowType::string($ticket->status);
        $ticketTitle  = RowType::string($ticket->title);

        $canManageDamagePoints = $ticketStatus !== 'closed'
            && ($this->roleHelper->isSupport() || ($isOwner && $ticketStatus === 'open'));

        $this->template->title                  = "Ticket #{$ticketId} — {$ticketTitle}";
        $this->template->ticket                 = $ticket;
        $this->template->item                   = $item;
        $this->template->creator                = $creator;
        $this->template->assignee               = $assignee;
        $this->template->images                 = $images;
        $this->template->blueprintPath          = $blueprintPath;
        $this->template->damagePoints           = $damagePoints;
        $this->template->damagePointsJson       = json_encode($damagePoints);
        $this->template->canManageDamagePoints  = $canManageDamagePoints;
        $this->template->canEdit                = ($isOwner && $ticketStatus === 'open') || $this->roleHelper->isAdmin();
        $this->template->canDeleteTicket        = $this->roleHelper->isAdmin();
        $this->template->canAssign              = $this->roleHelper->isAdmin();
        $this->template->canChangeStatus        = $this->roleHelper->isSupport();
        $this->template->canAddServiceRecord    = $this->roleHelper->isSupport();
        $this->template->canAddImage            = $this->roleHelper->isSupport() || ($isOwner && $ticketStatus === 'open');
        $this->template->canDeleteImage         = $this->roleHelper->isSupport();
    }

    // -- Assign form (admin) -----------------------------------------

    protected function createComponentAssignForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $supportRows = $this->userRepository->findByRole('support')
            ->where('status', 'approved')
            ->fetchAll();

        $pairs = [];
        foreach ($supportRows as $u) {
            $pairs[RowType::int($u->id)] = RowType::string($u->first_name) . ' ' . RowType::string($u->last_name);
        }

        $defaultAssigned = $this->targetTicket !== null
            ? RowType::nullableInt($this->targetTicket->assigned_to)
            : null;

        $form->addSelect('assigned_to', 'Assign to', $pairs)
            ->setPrompt('— select support agent —')
            ->setRequired('Please select a support agent.')
            ->setDefaultValue($defaultAssigned);

        $form->addSubmit('submit', 'Assign')
            ->setHtmlAttribute('class', 'btn btn-primary btn-sm');

        $form->onSuccess[] = [$this, 'assignFormSucceeded'];

        return $form;
    }

    public function assignFormSucceeded(Form $form, mixed $values): void
    {
        $ticket = $this->targetTicket ?? throw new \LogicException('Target ticket not loaded.');

        if (!$this->roleHelper->isAdmin()) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('detail', RowType::int($ticket->id));
        }

        try {
            $data = $form->getValues(true);
            $this->ticketFacade->assign(RowType::int($ticket->id), RowType::intValue($data['assigned_to']));
            $this->flashMessage('Ticket assigned successfully.', 'success');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to assign ticket: ' . $e->getMessage(), 'danger');
        }

        $this->redirect('detail', RowType::int($ticket->id));
    }

    // -- Change-status form (support + admin) ------------------------

    protected function createComponentChangeStatusForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $defaultStatus = $this->targetTicket !== null
            ? RowType::string($this->targetTicket->status)
            : 'open';

        $form->addSelect('status', 'New Status', [
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'closed'      => 'Closed',
        ])
            ->setRequired('Status is required.')
            ->setDefaultValue($defaultStatus);

        $form->addSubmit('submit', 'Update Status')
            ->setHtmlAttribute('class', 'btn btn-primary btn-sm');

        $form->onSuccess[] = [$this, 'changeStatusFormSucceeded'];

        return $form;
    }

    public function changeStatusFormSucceeded(Form $form, mixed $values): void
    {
        $ticket = $this->targetTicket ?? throw new \LogicException('Target ticket not loaded.');

        if (!$this->roleHelper->isSupport()) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('detail', RowType::int($ticket->id));
        }

        try {
            $data = $form->getValues(true);
            $this->ticketFacade->changeStatus(
                RowType::int($ticket->id),
                RowType::string($data['status']),
                $this->roleHelper->isAdmin(),
                (int) $this->getUser()->getId(),
            );
            $this->flashMessage('Ticket status updated.', 'success');
        } catch (\RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
        }

        $this->redirect('detail', RowType::int($ticket->id));
    }

    // -- Add service-record form (support + admin) -------------------

    protected function createComponentAddServiceRecordForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addTextArea('description', 'Service Record')
            ->setRequired('Description is required.')
            ->setHtmlAttribute('rows', 3)
            ->setHtmlAttribute('placeholder', 'Describe the service action performed…')
            ->setMaxLength(5000);

        $form->addSubmit('submit', 'Add Service Record')
            ->setHtmlAttribute('class', 'btn btn-outline-primary btn-sm');

        $form->onSuccess[] = [$this, 'addServiceRecordFormSucceeded'];

        return $form;
    }

    public function addServiceRecordFormSucceeded(Form $form, mixed $values): void
    {
        $ticket = $this->targetTicket ?? throw new \LogicException('Target ticket not loaded.');

        if (!$this->roleHelper->isSupport()) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('detail', RowType::int($ticket->id));
        }

        try {
            $data = $form->getValues(true);
            $this->itemFacade->addServiceRecord(
                RowType::int($ticket->item_id),
                RowType::string($data['description']),
                (int) $this->getUser()->getId(),
            );
            $this->flashMessage('Service record added to item.', 'success');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to add service record: ' . $e->getMessage(), 'danger');
        }

        $this->redirect('detail', RowType::int($ticket->id));
    }

    // -- Add image form (owner if open, or support+) -----------------

    protected function createComponentAddImageForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addMultiUpload('images', 'Add Images (JPG / PNG, max 5 MB each)')
            ->setHtmlAttribute('accept', 'image/jpeg,image/png');

        $form->addSubmit('submit', 'Upload')
            ->setHtmlAttribute('class', 'btn btn-outline-secondary btn-sm');

        $form->onSuccess[] = [$this, 'addImageFormSucceeded'];

        return $form;
    }

    public function addImageFormSucceeded(Form $form, mixed $values): void
    {
        $ticket  = $this->targetTicket ?? throw new \LogicException('Target ticket not loaded.');
        $userId  = (int) $this->getUser()->getId();
        $isOwner = RowType::int($ticket->created_by) === $userId;
        $ticketStatus = RowType::string($ticket->status);
        $canAdd  = $this->roleHelper->isSupport() || ($isOwner && $ticketStatus === 'open');
        $ticketId = RowType::int($ticket->id);

        if (!$canAdd) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('detail', $ticketId);
        }

        $uploaded = 0;
        $errors   = [];

        $data = $form->getValues(true);
        $imagesRaw = $data['images'] ?? null;
        if (is_iterable($imagesRaw)) {
            foreach ($imagesRaw as $file) {
                if (!$file instanceof FileUpload || !$file->isOk() || $file->getSize() === 0) {
                    continue;
                }
                try {
                    $this->ticketFacade->addImage($ticketId, $file);
                    $uploaded++;
                } catch (\RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        if ($uploaded > 0) {
            $this->flashMessage("{$uploaded} image(s) uploaded successfully.", 'success');
        }
        foreach ($errors as $err) {
            $this->flashMessage($err, 'danger');
        }

        $this->redirect('detail', $ticketId);
    }

    // -- Delete image signal -----------------------------------------

    public function handleDeleteImage(int $imageId): void
    {
        if (!$this->getHttpRequest()->isMethod('POST')) {
            $this->error('Method not allowed.', 405);
        }

        $ticket  = $this->targetTicket ?? throw new \LogicException('Target ticket not loaded.');
        $ticketId = RowType::int($ticket->id);

        $image = $this->ticketImageRepository->findById($imageId);
        if ($image === null) {
            $this->flashMessage('Image not found.', 'danger');
            $this->redirect('detail', $ticketId);
        }

        if (RowType::int($image->ticket_id) !== $ticketId) {
            $this->flashMessage('Image does not belong to this ticket.', 'danger');
            $this->redirect('detail', $ticketId);
        }

        $userId    = (int) $this->getUser()->getId();
        $isOwner   = RowType::int($ticket->created_by) === $userId;
        $ticketStatus = RowType::string($ticket->status);
        $canDelete = $this->roleHelper->isSupport() || ($isOwner && $ticketStatus === 'open');

        if (!$canDelete) {
            $this->flashMessage('You do not have permission to delete images.', 'danger');
            $this->redirect('detail', $ticketId);
        }

        try {
            $this->ticketFacade->deleteImage($imageId);
            $this->flashMessage('Image deleted.', 'success');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to delete image: ' . $e->getMessage(), 'danger');
        }

        $this->redirect('detail', $ticketId);
    }

    // ==================================================================
    //  EDIT
    // ==================================================================

    public function actionEdit(int $id): void
    {
        $ticket = $this->requireTicket($id);

        $userId  = (int) $this->getUser()->getId();
        $isOwner = RowType::int($ticket->created_by) === $userId;
        $canEdit = ($isOwner && RowType::string($ticket->status) === 'open') || $this->roleHelper->isAdmin();

        if (!$canEdit) {
            $this->flashMessage('You cannot edit this ticket.', 'danger');
            $this->redirect('detail', $id);
        }

        $this->targetTicket = $ticket;
    }

    public function renderEdit(int $id): void
    {
        $ticket = $this->targetTicket ?? throw new \LogicException('Target ticket not loaded.');

        $this->template->title        = "Edit Ticket #" . RowType::int($ticket->id);
        $this->template->targetTicket = $ticket;

        $this['editForm']->setDefaults([
            'title'       => $ticket->title,
            'description' => $ticket->description,
        ]);
    }

    protected function createComponentEditForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('title', 'Title')
            ->setRequired('Title is required.')
            ->setMaxLength(200);

        $form->addTextArea('description', 'Description')
            ->setRequired('Description is required.')
            ->setHtmlAttribute('rows', 6)
            ->setMaxLength(10000);

        $form->addSubmit('submit', 'Save Changes')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'editFormSucceeded'];

        return $form;
    }

    public function editFormSucceeded(Form $form, mixed $values): void
    {
        $ticket  = $this->targetTicket ?? throw new \LogicException('Target ticket not loaded.');
        $userId  = (int) $this->getUser()->getId();
        $isOwner = RowType::int($ticket->created_by) === $userId;
        $ticketStatus = RowType::string($ticket->status);
        $ticketId = RowType::int($ticket->id);
        $canEdit = ($isOwner && $ticketStatus === 'open') || $this->roleHelper->isAdmin();

        if (!$canEdit) {
            $this->flashMessage('You cannot edit this ticket.', 'danger');
            $this->redirect('detail', $ticketId);
        }

        try {
            $data = $form->getValues(true);
            $this->ticketFacade->update($ticketId, [
                'title'       => RowType::string($data['title']),
                'description' => RowType::string($data['description']),
            ]);
            $this->flashMessage('Ticket updated successfully.', 'success');
            $this->redirect('detail', $ticketId);
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to update ticket: ' . $e->getMessage(), 'danger');
        }
    }

    // ==================================================================
    //  DELETE
    // ==================================================================

    public function actionDelete(int $id): void
    {
        if (!$this->roleHelper->isAdmin()) {
            $this->flashMessage('You do not have permission to delete tickets.', 'danger');
            $this->redirect('detail', $id);
        }

        $this->targetTicket = $this->requireTicket($id);
    }

    public function renderDelete(int $id): void
    {
        $ticket = $this->targetTicket ?? throw new \LogicException('Target ticket not loaded.');
        $this->template->title        = "Delete Ticket #" . RowType::int($ticket->id);
        $this->template->targetTicket = $ticket;
    }

    protected function createComponentDeleteForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addSubmit('delete', 'Yes, Delete This Ticket')
            ->setHtmlAttribute('class', 'btn btn-danger');

        $form->onSuccess[] = [$this, 'deleteFormSucceeded'];

        return $form;
    }

    public function deleteFormSucceeded(Form $form, mixed $values): void
    {
        $ticket = $this->targetTicket ?? throw new \LogicException('Target ticket not loaded.');

        if (!$this->roleHelper->isAdmin()) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('default');
        }

        try {
            $ticketId = RowType::int($ticket->id);
            $title    = RowType::string($ticket->title);
            $this->ticketFacade->softDelete($ticketId);
            $this->flashMessage("Ticket #{$ticketId} \"{$title}\" has been deleted.", 'success');
            $this->redirect('default');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to delete ticket: ' . $e->getMessage(), 'danger');
            $this->redirect('delete', RowType::int($ticket->id));
        }
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    private function requireTicket(int $id): ActiveRow
    {
        $ticket = $this->ticketRepository->findById($id);

        if ($ticket === null) {
            $this->error('Ticket not found.', 404);
        }

        return $ticket;
    }
}
