<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Model\Database\RowType;
use App\Model\Facade\ItemFacade;
use App\Model\Repository\LocationRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;

final class LocationPresenter extends BasePresenter
{
    private ?ActiveRow $targetLocation = null;

    private ItemFacade         $itemFacade;
    private LocationRepository $locationRepository;

    public function injectItemFacade(ItemFacade $itemFacade): void
    {
        $this->itemFacade = $itemFacade;
    }

    public function injectLocationRepository(LocationRepository $locationRepository): void
    {
        $this->locationRepository = $locationRepository;
    }

    // ==================================================================
    //  LIST
    // ==================================================================

    public function renderDefault(): void
    {
        $this->template->title     = 'Locations';
        $this->template->locations = $this->locationRepository->findAllOrdered();
    }

    // ==================================================================
    //  CREATE
    // ==================================================================

    public function renderCreate(): void
    {
        $this->template->title = 'Create Location';
    }

    protected function createComponentCreateForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('name', 'Name')
            ->setRequired('Name is required.')
            ->setMaxLength(120)
            ->setHtmlAttribute('placeholder', 'e.g. Building A – Ground Floor');

        $form->addSubmit('submit', 'Create Location')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'createFormSucceeded'];

        return $form;
    }

    public function createFormSucceeded(Form $form, mixed $values): void
    {
        $data = $form->getValues(true);
        $name = RowType::string($data['name']);

        try {
            $this->itemFacade->createLocation($name);
            $this->flashMessage("Location \"{$name}\" created.", 'success');
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
        $this->targetLocation = $this->requireLocation($id);
    }

    public function renderEdit(int $id): void
    {
        $location     = $this->targetLocation ?? throw new \LogicException('Target location not loaded.');
        $locationName = RowType::string($location->name);

        $this->template->title          = "Edit Location — {$locationName}";
        $this->template->targetLocation = $location;

        $this['editForm']->setDefaults(['name' => $location->name]);
    }

    protected function createComponentEditForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addText('name', 'Name')
            ->setRequired('Name is required.')
            ->setMaxLength(120);

        $form->addSubmit('submit', 'Save Changes')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'editFormSucceeded'];

        return $form;
    }

    public function editFormSucceeded(Form $form, mixed $values): void
    {
        $location   = $this->targetLocation ?? throw new \LogicException('Target location not loaded.');
        $locationId = RowType::int($location->id);
        $data       = $form->getValues(true);
        $name       = RowType::string($data['name']);

        try {
            $this->itemFacade->updateLocation($locationId, $name);
            $this->flashMessage('Location updated.', 'success');
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
        $this->targetLocation = $this->requireLocation($id);
    }

    public function renderDelete(int $id): void
    {
        $this->template->title          = 'Delete Location';
        $this->template->targetLocation = $this->targetLocation;
        $this->template->hasItems       = $this->locationRepository->hasItems($id);
    }

    protected function createComponentDeleteForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        $form->addSubmit('delete', 'Yes, Delete This Location')
            ->setHtmlAttribute('class', 'btn btn-danger');

        $form->onSuccess[] = [$this, 'deleteFormSucceeded'];

        return $form;
    }

    public function deleteFormSucceeded(Form $form, mixed $values): void
    {
        $location   = $this->targetLocation ?? throw new \LogicException('Target location not loaded.');
        $locationId = RowType::int($location->id);
        $name       = RowType::string($location->name);

        try {
            $this->itemFacade->deleteLocation($locationId);
            $this->flashMessage("Location \"{$name}\" has been deleted.", 'success');
            $this->redirect('default');
        } catch (\RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
            $this->redirect('delete', $locationId);
        }
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    private function requireLocation(int $id): ActiveRow
    {
        $location = $this->locationRepository->findById($id);

        if ($location === null) {
            $this->error('Location not found.', 404);
        }

        return $location;
    }
}
