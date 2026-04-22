<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Model\Database\RowType;
use App\Model\Facade\ItemFacade;
use App\Model\Repository\ItemTypeRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Http\FileUpload;

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

    public function createFormSucceeded(Form $form, mixed $values): void
    {
        $data = $form->getValues(true);
        $name = RowType::string($data['name']);

        try {
            $this->itemFacade->createItemType($name);
            $this->flashMessage("Item type \"{$name}\" created.", 'success');
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
        $type     = $this->targetType ?? throw new \LogicException('Target type not loaded.');
        $typeName = RowType::string($type->name);

        $this->template->title      = "Edit Item Type — {$typeName}";
        $this->template->targetType = $type;

        $this['editForm']->setDefaults(['name' => $type->name]);
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

    public function editFormSucceeded(Form $form, mixed $values): void
    {
        $type   = $this->targetType ?? throw new \LogicException('Target type not loaded.');
        $typeId = RowType::int($type->id);
        $data   = $form->getValues(true);
        $name   = RowType::string($data['name']);

        try {
            $this->itemFacade->updateItemType($typeId, $name);

            $removeBlueprint = (bool) ($data['remove_blueprint'] ?? false);
            $blueprint       = $data['blueprint'] ?? null;

            if ($removeBlueprint) {
                $this->itemFacade->removeItemTypeBlueprint($typeId);
            } elseif ($blueprint instanceof FileUpload && $blueprint->isOk() && $blueprint->getSize() > 0) {
                $this->itemFacade->updateItemTypeBlueprint($typeId, $blueprint);
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

    public function deleteFormSucceeded(Form $form, mixed $values): void
    {
        $type   = $this->targetType ?? throw new \LogicException('Target type not loaded.');
        $typeId = RowType::int($type->id);
        $name   = RowType::string($type->name);

        try {
            $this->itemFacade->deleteItemType($typeId);
            $this->flashMessage("Item type \"{$name}\" has been deleted.", 'success');
            $this->redirect('default');
        } catch (\RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
            $this->redirect('delete', $typeId);
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
