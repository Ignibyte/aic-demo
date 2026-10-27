<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Form\VehicleDeletes;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * POST /app/inventory/{vin}/delete: deletes the vehicle when its envelope seals this vehicle's path and it is not sold,
 * then goes to the list (303); else the delete page again (422), 400 for the envelope, or 404.
 */
final readonly class VehicleDeletePost implements Page
{
    /**
     * @param VehicleDeletes $vehicleDeletes The delete's pages.
     */
    public function __construct(
        private VehicleDeletes $vehicleDeletes,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException           When the vehicle cannot be read or deleted.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws LogicException           When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the list, 422, 400 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleDeletes->delete($serverRequest);
    }
}
