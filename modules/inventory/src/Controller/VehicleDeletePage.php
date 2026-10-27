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
 * GET /app/inventory/{vin}/delete: asks before deleting the vehicle, or says a sold vehicle stays on the record; 404
 * for a VIN no vehicle has.
 */
final readonly class VehicleDeletePage implements Page
{
    /**
     * @param VehicleDeletes $vehicleDeletes The delete's pages.
     */
    public function __construct(
        private VehicleDeletes $vehicleDeletes,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException           When the vehicle cannot be read.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws LogicException           When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The confirmation, the sold vehicle's notice, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleDeletes->confirm($serverRequest);
    }
}
