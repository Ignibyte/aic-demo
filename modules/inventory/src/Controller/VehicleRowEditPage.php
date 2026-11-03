<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Form\VehicleRowEdits;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app/inventory/{vin}/row/edit (#28): the vehicle's row in the list as a small form, for a click on its price or
 * status; the inventory page holding that row alone, so Tessera gets the row. 404 for no such vehicle.
 */
final readonly class VehicleRowEditPage implements Page
{
    /**
     * @param VehicleRowEdits $vehicleRowEdits The row's flows.
     */
    public function __construct(
        private VehicleRowEdits $vehicleRowEdits,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for the row as a form, from a signed-in member of staff.
     *
     * @throws QueryException           When the vehicle cannot be read or written.
     * @throws UnexpectedValueException When a status is no status.
     * @throws LogicException           When the request has no session, a key or a value does not fit, or a region does not fit the theme.
     *
     * @return PageResponse The row as a form, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleRowEdits->form($serverRequest);
    }
}
