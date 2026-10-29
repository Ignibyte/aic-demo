<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Controller\Contracts\PageResponse;
use App\Inventory\Entity\Vehicle;
use App\Photos\Service\PhotoGalleries;
use App\Staff\Service\StaffNotices;
use App\Theme\Components\DetailCard\ButtonLink;
use App\Theme\Components\DetailCard\ButtonTone;
use App\Theme\Components\DetailCard\DetailCard;
use App\Theme\Components\DetailCard\Fact;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function number_format;

/**
 * A vehicle's page: the notice its last save left, then its card with its status, what it is, its mileage, list
 * price and day on the lot (never its cost), and buttons to edit it, delete it or go back to the list; then its photos
 * (#16), with the reason when an upload was refused; then its sale, when one is recorded, or, for a vehicle not sold, a
 * button to record one.
 */
final readonly class VehicleCards
{
    /**
     * @param VehicleRecords  $vehicleRecords  The route's vehicle, the 404 page and the vehicle pages' frame.
     * @param SoldVehicles    $soldVehicles    The vehicle's sale, and where to record one.
     * @param PhotoGalleries  $photoGalleries  The vehicle's photos.
     * @param VehicleStatuses $vehicleStatuses Gives the status its badge.
     * @param StaffNotices    $staffNotices    The last save's notice.
     */
    public function __construct(
        private VehicleRecords $vehicleRecords,
        private SoldVehicles $soldVehicles,
        private PhotoGalleries $photoGalleries,
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a vehicle's page, or a post to one of its routes, with
     *                                              its session.
     * @param string                 $refusal       Why a photo upload was refused, shown in the gallery; empty for none.
     * @param int                    $status        200, or 422 after a refused upload.
     *
     * @throws LogicException   When the request has no session, or a key does not fit the theme.
     * @throws RuntimeException When the vehicle, its photos or its sale cannot be read, or its stored status or the
     *                          member's role is not one.
     *
     * @return PageResponse The vehicle's page, or 404.
     */
    public function page(ServerRequestInterface $serverRequest, string $refusal = '', int $status = 200): PageResponse
    {
        // The notice is taken first, so a save whose vehicle went before its 303 landed leaves nothing behind.
        $body = $this->staffNotices->take($serverRequest, 'vehicle-notice');
        $vehicle = $this->vehicleRecords->find($serverRequest);

        if (!$vehicle instanceof Vehicle) {
            return $this->vehicleRecords->missing();
        }

        $vehicleSale = $this->soldVehicles->sale($vehicle->vin);
        $body['card'] = $this->card($vehicle, $this->soldVehicles->recordPath($vehicle, $vehicleSale));
        $vehicleId = $this->vehicleRecords->vehicleId($serverRequest);
        $body['photos'] = $this->photoGalleries->gallery($serverRequest, $vehicleId, $vehicle->path(), $vehicle->title(), $refusal);

        foreach ($this->soldVehicles->tables($vehicleSale) as $key => $dataTable) {
            $body[$key] = $dataTable;
        }

        $staffPanels = $this->vehicleRecords->panels();

        return $staffPanels->page($vehicle->title(), 'Vehicle record', 'What the lot keeps on this vehicle.', $body, $status);
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
