<?php

declare(strict_types=1);

namespace App\Modules\Api\Presenters;

use App\Model\Facade\DamagePointFacade;
use App\Security\RoleHelper;

/**
 * AJAX API endpoints for ticket damage points.
 *
 * Routes (added in RouterFactory):
 *   POST /api/damage-point/add           — add a damage point
 *   POST /api/damage-point/remove/{id}   — remove a damage point
 *
 * Every endpoint requires:
 *   - X-Requested-With: XMLHttpRequest   (CSRF mitigation)
 *   - Authenticated session (employee or above)
 *
 * All permission / ownership logic is delegated to DamagePointFacade.
 */
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
    //
    //  Expected JSON body:
    //    { "ticket_id": int, "position_x": float, "position_y": float, "description": string }
    //
    //  Success response:
    //    { "success": true, "point": { "id": int, "position_x": float, "position_y": float, "description": string } }
    // ==================================================================

    public function actionAdd(): void
    {
        $this->requireXhr();
        $this->requirePost();
        $this->requireLogin();

        if (!$this->roleHelper->isEmployee()) {
            $this->sendJsonError('Permission denied.', 403);
        }

        $body     = (string) $this->getHttpRequest()->getRawBody();
        $data     = json_decode($body, true);

        if (!is_array($data)) {
            $this->sendJsonError('Invalid JSON body.');
        }

        $ticketId    = isset($data['ticket_id']) ? (int)   $data['ticket_id'] : 0;
        $x           = isset($data['position_x']) ? (float) $data['position_x'] : -1.0;
        $y           = isset($data['position_y']) ? (float) $data['position_y'] : -1.0;
        $description = isset($data['description']) ? trim((string) $data['description']) : '';

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
                    'id'          => (int)    $point->id,
                    'position_x'  => (float)  $point->position_x,
                    'position_y'  => (float)  $point->position_y,
                    'description' => (string) $point->description,
                ],
            ]);
        } catch (\RuntimeException $e) {
            $this->sendJsonError($e->getMessage());
        }
    }

    // ==================================================================
    //  POST /api/damage-point/remove/{id}
    //
    //  Success response: { "success": true }
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
