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
 * POST /app/inventory/{vin}/delete: deletes the vehicle when its envelope seals this vehicle's path and it is not sold,
 * then goes to the list (303); else the delete page again (422), 400 for the envelope, or 404.
 */
final readonly class VehicleDeletePost implements Page
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
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws RuntimeException When the vehicle or its photos cannot be read, it cannot be deleted (`QueryException`),
     *                          or its stored status is no status (`UnexpectedValueException`).
     * @throws LogicException   When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the list, 422, 400 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleDeletes->delete($serverRequest);
    }
}
