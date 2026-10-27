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
 * GET /app/inventory/{vin}/edit: the vehicle form filled from the vehicle, its envelope sealing the vehicle's path;
 * 404 for a VIN no vehicle has.
 */
final readonly class VehicleEditPage implements Page
{
    /**
     * @param VehicleSaves $vehicleSaves The form's flows.
     */
    public function __construct(
        private VehicleSaves $vehicleSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException           When the vehicle cannot be read.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws LogicException           When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The form, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleSaves->edit($serverRequest);
    }
}
