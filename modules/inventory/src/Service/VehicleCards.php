<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPanels;
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
 * price and day on the lot (never its cost), and buttons to edit it, delete it or go back to the list; then its sale,
 * when one is recorded, or, for a vehicle not sold, a button to record one.
 */
final readonly class VehicleCards
{
    /**
     * @param VehicleRecords  $vehicleRecords  The route's vehicle and the 404 page.
     * @param StaffPanels     $staffPanels     The vehicle pages' frame.
     * @param SoldVehicles    $soldVehicles    The vehicle's sale, and where to record one.
     * @param VehicleStatuses $vehicleStatuses Gives the status its badge.
     * @param StaffNotices    $staffNotices    The last save's notice.
     */
    public function __construct(
        private VehicleRecords $vehicleRecords,
        private StaffPanels $staffPanels,
        private SoldVehicles $soldVehicles,
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a vehicle's page, with its session.
     *
     * @throws QueryException           When the vehicle or its sale cannot be read.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws LogicException           When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse The vehicle's page, or 404.
     */
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        // The notice is taken first, so a save whose vehicle went before its 303 landed leaves nothing behind.
        $body = $this->staffNotices->take($serverRequest, 'vehicle-notice');
        $vehicle = $this->vehicleRecords->find($serverRequest);

        if (!$vehicle instanceof Vehicle) {
            return $this->vehicleRecords->missing();
        }

        $vehicleSale = $this->soldVehicles->sale($vehicle->vin);
        $body['card'] = $this->card($vehicle, $this->soldVehicles->recordPath($vehicle, $vehicleSale));

        foreach ($this->soldVehicles->tables($vehicleSale) as $key => $dataTable) {
            $body[$key] = $dataTable;
        }

        return $this->staffPanels->page($vehicle->title(), 'Vehicle record', 'What the lot keeps on this vehicle.', $body, 200);
    }

    /**
     * @param Vehicle $vehicle    A vehicle.
     * @param string  $recordPath Where its sale is recorded; empty for no button.
     *
     * @return DetailCard Its card.
     */
    private function card(Vehicle $vehicle, string $recordPath): DetailCard
    {
        $path = $vehicle->path();
        $actions = [new ButtonLink('Edit', $path . '/edit', 'vehicle-edit', ButtonTone::Primary)];

        if ($recordPath !== '') {
            $actions[] = new ButtonLink('Record a sale', $recordPath, 'vehicle-sell', ButtonTone::Secondary);
        }

        $actions[] = new ButtonLink('Delete', $path . '/delete', 'vehicle-delete', ButtonTone::Error);
        $actions[] = new ButtonLink('Back to the list', Vehicle::PAGES, 'vehicle-back', ButtonTone::Ghost);

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
        ], '', $actions);
    }
}
