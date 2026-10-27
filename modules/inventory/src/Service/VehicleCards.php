<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Theme\Components\DetailCard\ButtonLink;
use App\Theme\Components\DetailCard\ButtonTone;
use App\Theme\Components\DetailCard\DetailCard;
use App\Theme\Components\DetailCard\Fact;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function number_format;

/**
 * A vehicle's page: the notice its last save left, then its card with its status, what it is, its mileage, list
 * price and day on the lot (never its cost), and buttons to edit it, delete it or go back to the list.
 */
final readonly class VehicleCards
{
    /**
     * @param VehicleRecords  $vehicleRecords  The route's vehicle and the 404 page.
     * @param VehiclePanels   $vehiclePanels   The vehicle pages' frame.
     * @param VehicleStatuses $vehicleStatuses Gives the status its badge.
     * @param VehicleNotices  $vehicleNotices  The last save's notice.
     */
    public function __construct(
        private VehicleRecords $vehicleRecords,
        private VehiclePanels $vehiclePanels,
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
        private VehicleNotices $vehicleNotices = new VehicleNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a vehicle's page, with its session.
     *
     * @throws QueryException           When the vehicle cannot be read.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws LogicException           When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse The vehicle's page, or 404.
     */
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $vehicle = $this->vehicleRecords->find($serverRequest);

        if (!$vehicle instanceof Vehicle) {
            return $this->vehicleRecords->missing();
        }

        $body = $this->vehicleNotices->take($serverRequest, 'vehicle-notice');
        $body['card'] = $this->card($vehicle);

        return $this->vehiclePanels->page($vehicle->title(), 'Vehicle record', 'What the lot keeps on this vehicle.', $body, 200);
    }

    /**
     * @param Vehicle $vehicle A vehicle.
     *
     * @return DetailCard Its card.
     */
    private function card(Vehicle $vehicle): DetailCard
    {
        $path = $vehicle->path();

        return new DetailCard('vehicle-title', $vehicle->title(), $this->vehicleStatuses->badge($vehicle->status, 'vehicle-status'), [
            new Fact('VIN', $vehicle->vin, true),
            new Fact('Year', (string) $vehicle->year, false),
            new Fact('Make', $vehicle->make, false),
            new Fact('Model', $vehicle->model, false),
            new Fact('Trim', $vehicle->trim === '' ? 'None' : $vehicle->trim, false),
            new Fact('Body style', $vehicle->bodyStyle, false),
            new Fact('Exterior colour', $vehicle->exteriorColour, false),
            new Fact('Mileage', number_format($vehicle->mileage) . ' mi', false),
            new Fact('List price', '$' . number_format($vehicle->price->list), false),
            new Fact('On the lot since', $vehicle->addedOn, false),
        ], '', [
            new ButtonLink('Edit', $path . '/edit', 'vehicle-edit', ButtonTone::Primary),
            new ButtonLink('Delete', $path . '/delete', 'vehicle-delete', ButtonTone::Error),
            new ButtonLink('Back to the list', Vehicle::PAGES, 'vehicle-back', ButtonTone::Ghost),
        ]);
    }
}
