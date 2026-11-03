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
 * PUT /app/inventory/{vin}/row (#28): saves the row's list price and status when its props seal this vehicle's path
 * and the vehicle form's checks pass, then answers the row shown; else the row as a form again (422), 400 for the props,
 * or 404. AIC's form guard has checked the CSRF token before it is routed.
 */
final readonly class VehicleRowPut implements Page
{
    /**
     * @param VehicleRowEdits $vehicleRowEdits The row's flows.
     */
    public function __construct(
        private VehicleRowEdits $vehicleRowEdits,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The row's save, with its fields, props and session.
     *
     * @throws QueryException           When the vehicle cannot be read or written.
     * @throws UnexpectedValueException When a status is no status.
     * @throws LogicException           When the request has no session, a key or a value does not fit, or a region does not fit the theme.
     *
     * @return PageResponse The row shown, 422, 400 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleRowEdits->save($serverRequest);
    }
}
