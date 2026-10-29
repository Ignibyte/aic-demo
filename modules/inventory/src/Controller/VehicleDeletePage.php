<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Inventory\Form\VehicleDeletes;
use App\Inventory\Service\VehicleServices;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /app/inventory/{vin}/delete: asks before deleting the vehicle, or says a sold vehicle stays on the record; 404
 * for a VIN no vehicle has.
 */
final readonly class VehicleDeletePage implements Page
{
    /**
     * The delete's pages.
     */
    private VehicleDeletes $vehicleDeletes;

    /**
     * @param VehicleServices $vehicleServices The vehicle's services, the delete's pages among them: taken whole, so
     *                                         the seven vehicle routes' generated wiring fits one method (#23).
     */
    public function __construct(
        VehicleServices $vehicleServices,
    ) {
        $this->vehicleDeletes = $vehicleServices->vehicleDeletes();
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws RuntimeException When the vehicle cannot be read (`QueryException`), or its stored status is no status
     *                          (`UnexpectedValueException`).
     * @throws LogicException   When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The confirmation, the sold vehicle's notice, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleDeletes->confirm($serverRequest);
    }
}
