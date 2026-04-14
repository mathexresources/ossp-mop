<?php

declare(strict_types=1);

namespace App\Modules\Front\Presenters;

use App\Core\SecuredPresenter;
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
use Nette\Utils\Paginator;

/**
 * Front-end ticket presenter.
 *
 * Access: all approved users (requiredRole = null).
 * Finer-grained guards are enforced per-action / per-form-handler.
 *
 * Actions:
 *   default       — paginated, filterable ticket list
 *   create        — new ticket with image upload (employee+)
 *   detail        — ticket detail with role-aware action panels
 *   edit          — edit title/description (owner if open, or admin)
 *   delete        — confirmation + soft-delete (admin only)
 */
final class TicketPresenter extends SecuredPresenter
{
    protected ?string $requiredRole = null;

    private const TICKETS_PER_PAGE = 20;

    private ?ActiveRow $targetTicket = null;

    private TicketFacade            $ticketFacade;
    private ItemFacade              $itemFacade;
    private DamagePointFacade       $damagePointFacade;
    private TicketRepository        $ticketRepository;
    private TicketImageRepository   $ticketImageRepository;
    private ItemRepository          $itemRepository;
    private ItemTypeRepository      $itemTypeRepository;
    private UserRepository          $userRepository;

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
        $status = trim((string) ($this->getParameter('status') ?? ''));
        $itemId = (int) ($this->getParameter('itemId') ?? 0);
        $search = trim((string) ($this->getParameter('search') ?? ''));

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

    public function createFormSucceeded(Form $form, \stdClass $values): void
    {
        if (!$this->getUser()->isAllowed('ticket', 'create')) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('default');
        }

        try {
            $ticket = $this->ticketFacade->create(
                [
                    'title'       => $values->title,
                    'description' => $values->description,
                    'item_id'     => $values->item_id,
                ],
                (int) $this->getUser()->getId(),
            );

            // Handle optional image uploads.
            $uploadErrors = [];
            foreach ($values->images as $file) {
                if (!$file->isOk() || $file->getSize() === 0) {
                    continue;
                }
                try {
                    $this->ticketFacade->addImage((int) $ticket->id, $file);
                } catch (\RuntimeException $e) {
                    $uploadErrors[] = $e->getMessage();
                }
            }

            foreach ($uploadErrors as $err) {
                $this->flashMessage($err, 'warning');
            }

            $this->flashMessage("Ticket #{$ticket->id} created successfully.", 'success');
            $this->redirect('detail', $ticket->id);
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
        $ticket  = $this->targetTicket;
        $userId  = (int) $this->getUser()->getId();
        $isOwner = (int) $ticket->created_by === $userId;

        $item     = $this->itemRepository->findById((int) $ticket->item_id);
        $creator  = $this->userRepository->findById((int) $ticket->created_by);
        $assignee = $ticket->assigned_to
            ? $this->userRepository->findById((int) $ticket->assigned_to)
            : null;
        $images   = $this->ticketImageRepository->findByTicket((int) $ticket->id)->fetchAll();

        // Resolve blueprint from the item's type (shared across all items of the same type).
        $blueprintPath = null;
        if ($item !== null) {
            $itemType = $this->itemTypeRepository->findById((int) $item->item_type_id);
            if ($itemType !== null && $itemType->blueprint_path) {
                $blueprintPath = $itemType->blueprint_path;
            }
        }

        $damagePoints = $this->damagePointFacade->getForTicket((int) $ticket->id);

        // Damage points are editable when:
        //   - Ticket is NOT closed
        //   - AND (support/admin, OR owner when ticket is open)
        $canManageDamagePoints = $ticket->status !== 'closed'
            && ($this->roleHelper->isSupport() || ($isOwner && $ticket->status === 'open'));

        $this->template->title                  = "Ticket #{$ticket->id} — {$ticket->title}";
        $this->template->ticket                 = $ticket;
        $this->template->item                   = $item;
        $this->template->creator                = $creator;
        $this->template->assignee               = $assignee;
        $this->template->images                 = $images;
        $this->template->blueprintPath          = $blueprintPath;
        $this->template->damagePoints           = $damagePoints;
        $this->template->damagePointsJson       = json_encode($damagePoints);
        $this->template->canManageDamagePoints  = $canManageDamagePoints;
        $this->template->canEdit                = ($isOwner && $ticket->status === 'open') || $this->roleHelper->isAdmin();
        $this->template->canDeleteTicket        = $this->roleHelper->isAdmin();
        $this->template->canAssign              = $this->roleHelper->isAdmin();
        $this->template->canChangeStatus        = $this->roleHelper->isSupport();
        $this->template->canAddServiceRecord    = $this->roleHelper->isSupport();
        $this->template->canAddImage            = $this->roleHelper->isSupport() || ($isOwner && $ticket->status === 'open');
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
            $pairs[(int) $u->id] = $u->first_name . ' ' . $u->last_name;
        }

        $form->addSelect('assigned_to', 'Assign to', $pairs)
            ->setPrompt('— select support agent —')
            ->setRequired('Please select a support agent.')
            ->setDefaultValue($this->targetTicket?->assigned_to);

        $form->addSubmit('submit', 'Assign')
            ->setHtmlAttribute('class', 'btn btn-primary btn-sm');

        $form->onSuccess[] = [$this, 'assignFormSucceeded'];

        return $form;
    }

    public function assignFormSucceeded(Form $form, \stdClass $values): void
    {
        if (!$this->roleHelper->isAdmin()) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('detail', $this->targetTicket->id);
        }

        try {
            $this->ticketFacade->assign((int) $this->targetTicket->id, (int) $values->assigned_to);
            $this->flashMessage('Ticket assigned successfully.', 'success');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to assign ticket: ' . $e->getMessage(), 'danger');
        }

        $this->redirect('detail', $this->targetTicket->id);
    }

    // -- Change-status form (support + admin) ------------------------

    protected function createComponentChangeStatusForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addSelect('status', 'New Status', [
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'closed'      => 'Closed',
        ])
            ->setRequired('Status is required.')
            ->setDefaultValue($this->targetTicket?->status ?? 'open');

        $form->addSubmit('submit', 'Update Status')
            ->setHtmlAttribute('class', 'btn btn-primary btn-sm');

        $form->onSuccess[] = [$this, 'changeStatusFormSucceeded'];

        return $form;
    }

    public function changeStatusFormSucceeded(Form $form, \stdClass $values): void
    {
        if (!$this->roleHelper->isSupport()) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('detail', $this->targetTicket->id);
        }

        try {
            $this->ticketFacade->changeStatus(
                (int) $this->targetTicket->id,
                $values->status,
                $this->roleHelper->isAdmin(),
                (int) $this->getUser()->getId(),
            );
            $this->flashMessage('Ticket status updated.', 'success');
        } catch (\RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
        }

        $this->redirect('detail', $this->targetTicket->id);
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

    public function addServiceRecordFormSucceeded(Form $form, \stdClass $values): void
    {
        if (!$this->roleHelper->isSupport()) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('detail', $this->targetTicket->id);
        }

        try {
            $this->itemFacade->addServiceRecord(
                (int) $this->targetTicket->item_id,
                $values->description,
                (int) $this->getUser()->getId(),
            );
            $this->flashMessage('Service record added to item.', 'success');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to add service record: ' . $e->getMessage(), 'danger');
        }

        $this->redirect('detail', $this->targetTicket->id);
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

    public function addImageFormSucceeded(Form $form, \stdClass $values): void
    {
        $ticket = $this->targetTicket;
        $userId = (int) $this->getUser()->getId();
        $isOwner = (int) $ticket->created_by === $userId;
        $canAdd  = $this->roleHelper->isSupport() || ($isOwner && $ticket->status === 'open');

        if (!$canAdd) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('detail', $ticket->id);
        }

        $uploaded = 0;
        $errors   = [];

        foreach ($values->images as $file) {
            if (!$file->isOk() || $file->getSize() === 0) {
                continue;
            }
            try {
                $this->ticketFacade->addImage((int) $ticket->id, $file);
                $uploaded++;
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($uploaded > 0) {
            $this->flashMessage("{$uploaded} image(s) uploaded successfully.", 'success');
        }
        foreach ($errors as $err) {
            $this->flashMessage($err, 'danger');
        }

        $this->redirect('detail', $ticket->id);
    }

    // -- Delete image signal -----------------------------------------

    /**
     * Signal handler for inline image deletion.
     * Triggered via a small POST form in the detail template.
     */
    public function handleDeleteImage(int $imageId): void
    {
        if (!$this->getHttpRequest()->isMethod('POST')) {
            $this->error('Method not allowed.', 405);
        }

        $image = $this->ticketImageRepository->findById($imageId);
        if ($image === null) {
            $this->flashMessage('Image not found.', 'danger');
            $this->redirect('detail', $this->targetTicket->id);
            return;
        }

        // Verify the image belongs to the current ticket.
        if ((int) $image->ticket_id !== (int) $this->targetTicket->id) {
            $this->flashMessage('Image does not belong to this ticket.', 'danger');
            $this->redirect('detail', $this->targetTicket->id);
            return;
        }

        $userId  = (int) $this->getUser()->getId();
        $isOwner = (int) $this->targetTicket->created_by === $userId;
        $canDelete = $this->roleHelper->isSupport()
            || ($isOwner && $this->targetTicket->status === 'open');

        if (!$canDelete) {
            $this->flashMessage('You do not have permission to delete images.', 'danger');
            $this->redirect('detail', $this->targetTicket->id);
            return;
        }

        try {
            $this->ticketFacade->deleteImage($imageId);
            $this->flashMessage('Image deleted.', 'success');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to delete image: ' . $e->getMessage(), 'danger');
        }

        $this->redirect('detail', $this->targetTicket->id);
    }

    // ==================================================================
    //  EDIT
    // ==================================================================

    public function actionEdit(int $id): void
    {
        $ticket = $this->requireTicket($id);

        $userId  = (int) $this->getUser()->getId();
        $isOwner = (int) $ticket->created_by === $userId;
        $canEdit = ($isOwner && $ticket->status === 'open') || $this->roleHelper->isAdmin();

        if (!$canEdit) {
            $this->flashMessage('You cannot edit this ticket.', 'danger');
            $this->redirect('detail', $id);
        }

        $this->targetTicket = $ticket;
    }

    public function renderEdit(int $id): void
    {
        $ticket = $this->targetTicket;

        $this->template->title        = "Edit Ticket #{$ticket->id}";
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

    public function editFormSucceeded(Form $form, \stdClass $values): void
    {
        $ticket  = $this->targetTicket;
        $userId  = (int) $this->getUser()->getId();
        $isOwner = (int) $ticket->created_by === $userId;
        $canEdit = ($isOwner && $ticket->status === 'open') || $this->roleHelper->isAdmin();

        if (!$canEdit) {
            $this->flashMessage('You cannot edit this ticket.', 'danger');
            $this->redirect('detail', $ticket->id);
        }

        try {
            $this->ticketFacade->update((int) $ticket->id, [
                'title'       => $values->title,
                'description' => $values->description,
            ]);
            $this->flashMessage('Ticket updated successfully.', 'success');
            $this->redirect('detail', $ticket->id);
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
        $this->template->title        = "Delete Ticket #{$this->targetTicket->id}";
        $this->template->targetTicket = $this->targetTicket;
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

    public function deleteFormSucceeded(Form $form, \stdClass $values): void
    {
        if (!$this->roleHelper->isAdmin()) {
            $this->flashMessage('Permission denied.', 'danger');
            $this->redirect('default');
        }

        try {
            $ticketId = (int) $this->targetTicket->id;
            $title    = $this->targetTicket->title;
            $this->ticketFacade->softDelete($ticketId);
            $this->flashMessage("Ticket #{$ticketId} \"{$title}\" has been deleted.", 'success');
            $this->redirect('default');
        } catch (\Throwable $e) {
            $this->flashMessage('Failed to delete ticket: ' . $e->getMessage(), 'danger');
            $this->redirect('delete', $this->targetTicket->id);
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
