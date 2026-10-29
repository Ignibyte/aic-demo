<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Inventory\Service\VehicleCards;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /app/inventory/{vin}: a vehicle's page, with the notice its last save left; 404 for a VIN no vehicle has. The
 * prefix is protected, so only signed-in staff reach it.
 */
final readonly class VehiclePage implements Page
{
    /**
     * @param VehicleCards $vehicleCards The vehicle's page.
     */
    public function __construct(
        private VehicleCards $vehicleCards,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws LogicException   When the request has no session, or a key does not fit the theme.
     * @throws RuntimeException When the vehicle, its photos or its sale cannot be read, or its stored status or the
     *                          member's role is not one.
     *
     * @return PageResponse The vehicle's page, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleCards->page($serverRequest);
    }
}
