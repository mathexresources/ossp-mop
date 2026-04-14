<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Model\Facade\ItemFacade;
use App\Model\Repository\ItemTypeRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;

/**
 * Admin CRUD for item types.
 *
 * Actions:
 *   default — list all item types
 *   create  — add a new item type
 *   edit    — rename an item type + manage its blueprint image
 *   delete  — confirm and delete (blocked when items are assigned)
 */
final class ItemTypePresenter extends BasePresenter
{
    private ?ActiveRow $targetType = null;

    private ItemFacade         $itemFacade;
    private ItemTypeRepository $itemTypeRepository;

    public function injectItemFacade(ItemFacade $itemFacade): void
    {
        $this->itemFacade = $itemFacade;
    }

    public function injectItemTypeRepository(ItemTypeRepository $itemTypeRepository): void
    {
        $this->itemTypeRepository = $itemTypeRepository;
    }

    // ==================================================================
    //  LIST
    // ==================================================================

    public function renderDefault(): void
    {
        $this->template->title     = 'Item Types';
        $this->template->itemTypes = $this->itemTypeRepository->findAllOrdered();
    }

    // ==================================================================
    //  CREATE
    // ==================================================================

    public function renderCreate(): void
    {
        $this->template->title = 'Create Item Type';
    }

    protected function createComponentCreateForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('name', 'Name')
            ->setRequired('Name is required.')
            ->setMaxLength(80)
            ->setHtmlAttribute('placeholder', 'e.g. Laptop, Printer, UPS…');

        $form->addSubmit('submit', 'Create Item Type')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'createFormSucceeded'];

        return $form;
    }

    public function createFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->itemFacade->createItemType($values->name);
            $this->flashMessage("Item type \"{$values->name}\" created.", 'success');
            $this->redirect('default');
        } catch (\RuntimeException $e) {
            $form->addError($e->getMessage());
        }
    }

    // ==================================================================
    //  EDIT
    // ==================================================================

    public function actionEdit(int $id): void
    {
        $this->targetType = $this->requireType($id);
    }

    public function renderEdit(int $id): void
    {
        $this->template->title      = "Edit Item Type — {$this->targetType->name}";
        $this->template->targetType = $this->targetType;

        $this['editForm']->setDefaults(['name' => $this->targetType->name]);
    }

    protected function createComponentEditForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('name', 'Name')
            ->setRequired('Name is required.')
            ->setMaxLength(80);

        $form->addUpload('blueprint', 'Blueprint image (JPG / PNG, max 10 MB)')
            ->setHtmlAttribute('accept', 'image/jpeg,image/png')
            ->setRequired(false);

        $form->addCheckbox('remove_blueprint', 'Remove current blueprint');

        $form->addSubmit('submit', 'Save Changes')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'editFormSucceeded'];

        return $form;
    }

    public function editFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            // Always update the name.
            $this->itemFacade->updateItemType($this->targetType->id, $values->name);

            // Blueprint: remove takes priority over a simultaneous upload.
            if ($values->remove_blueprint) {
                $this->itemFacade->removeItemTypeBlueprint($this->targetType->id);
            } elseif (isset($values->blueprint) && $values->blueprint->isOk() && $values->blueprint->getSize() > 0) {
                $this->itemFacade->updateItemTypeBlueprint($this->targetType->id, $values->blueprint);
            }

            $this->flashMessage('Item type updated.', 'success');
            $this->redirect('default');
        } catch (\RuntimeException $e) {
            $form->addError($e->getMessage());
        }
    }

    // ==================================================================
    //  DELETE
    // ==================================================================

    public function actionDelete(int $id): void
    {
        $this->targetType = $this->requireType($id);
    }

    public function renderDelete(int $id): void
    {
        $this->template->title      = 'Delete Item Type';
        $this->template->targetType = $this->targetType;
        $this->template->hasItems   = $this->itemTypeRepository->hasItems($id);
    }

    protected function createComponentDeleteForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addSubmit('delete', 'Yes, Delete This Item Type')
            ->setHtmlAttribute('class', 'btn btn-danger');

        $form->onSuccess[] = [$this, 'deleteFormSucceeded'];

        return $form;
    }

    public function deleteFormSucceeded(Form $form, \stdClass $values): void
    {
        $name = $this->targetType->name;

        try {
            $this->itemFacade->deleteItemType($this->targetType->id);
            $this->flashMessage("Item type \"{$name}\" has been deleted.", 'success');
            $this->redirect('default');
        } catch (\RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
            $this->redirect('delete', $this->targetType->id);
        }
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    private function requireType(int $id): ActiveRow
    {
        $type = $this->itemTypeRepository->findById($id);

        if ($type === null) {
            $this->error('Item type not found.', 404);
        }

        return $type;
    }
}
