<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Form\VehicleSaves;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * POST /app/inventory/{vin}: saves the edit form when its envelope seals this vehicle's path and every field passes,
 * then goes to the vehicle (303); else the form again (422), 400 for the envelope, or 404.
 */
final readonly class VehicleUpdatePost implements Page
{
    /**
     * @param VehicleSaves $vehicleSaves The form's flows.
     */
    public function __construct(
        private VehicleSaves $vehicleSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException           When the vehicles cannot be read or written.
     * @throws UnexpectedValueException When a status is no status.
     * @throws LogicException           When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the vehicle, 422, 400 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleSaves->update($serverRequest);
    }
}
