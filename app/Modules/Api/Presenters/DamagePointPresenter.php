<?php

declare(strict_types=1);

namespace App\Modules\Api\Presenters;

use App\Model\Database\RowType;
use App\Model\Facade\DamagePointFacade;
use App\Security\RoleHelper;

final class DamagePointPresenter extends BasePresenter
{
    private DamagePointFacade $damagePointFacade;
    private RoleHelper        $roleHelper;

    public function injectDamagePointFacade(DamagePointFacade $facade): void
    {
        $this->damagePointFacade = $facade;
    }

    public function injectRoleHelper(RoleHelper $roleHelper): void
    {
        $this->roleHelper = $roleHelper;
    }

    // ==================================================================
    //  POST /api/damage-point/add
    // ==================================================================

    public function actionAdd(): void
    {
        $this->requireXhr();
        $this->requirePost();
        $this->requireLogin();

        if (!$this->roleHelper->isEmployee()) {
            $this->sendJsonError('Permission denied.', 403);
        }

        $body = (string) $this->getHttpRequest()->getRawBody();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            $this->sendJsonError('Invalid JSON body.');
        }

        $ticketIdRaw = $data['ticket_id'] ?? null;
        $ticketId    = is_numeric($ticketIdRaw) ? (int) $ticketIdRaw : 0;
        $xRaw        = $data['position_x'] ?? null;
        $x           = is_numeric($xRaw) ? (float) $xRaw : -1.0;
        $yRaw        = $data['position_y'] ?? null;
        $y           = is_numeric($yRaw) ? (float) $yRaw : -1.0;
        $descRaw     = $data['description'] ?? null;
        $description = is_string($descRaw) ? trim($descRaw) : '';

        if ($ticketId <= 0) {
            $this->sendJsonError('Invalid ticket_id.');
        }

        try {
            $point = $this->damagePointFacade->addPoint(
                $ticketId,
                $x,
                $y,
                $description,
                (int) $this->getUser()->getId(),
                $this->roleHelper->isAdmin(),
                $this->roleHelper->isSupport(),
            );

            $this->sendJson([
                'success' => true,
                'point'   => [
                    'id'          => RowType::int($point->id),
                    'position_x'  => RowType::float($point->position_x),
                    'position_y'  => RowType::float($point->position_y),
                    'description' => RowType::string($point->description),
                ],
            ]);
        } catch (\RuntimeException $e) {
            $this->sendJsonError($e->getMessage());
        }
    }

    // ==================================================================
    //  POST /api/damage-point/remove/{id}
    // ==================================================================

    public function actionRemove(int $id): void
    {
        $this->requireXhr();
        $this->requirePost();
        $this->requireLogin();

        if (!$this->roleHelper->isEmployee()) {
            $this->sendJsonError('Permission denied.', 403);
        }

        try {
            $this->damagePointFacade->removePoint(
                $id,
                (int) $this->getUser()->getId(),
                $this->roleHelper->isAdmin(),
                $this->roleHelper->isSupport(),
            );

            $this->sendJson(['success' => true]);
        } catch (\RuntimeException $e) {
            $this->sendJsonError($e->getMessage());
        }
    }
}
