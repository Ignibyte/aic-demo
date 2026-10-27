<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleSale;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Repository\VehicleRecordRepository;
use App\Inventory\Service\SoldVehicles;
use App\Inventory\Service\VehicleRecords;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffNotices;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * Deleting a vehicle: the page that asks first, and the delete itself, which keeps a sold vehicle (422), leaves a
 * notice for the list and goes there (303).
 */
final readonly class VehicleDeletes
{
    /**
     * @param VehicleRecords          $vehicleRecords          The route's vehicle and the 404 and 400 pages.
     * @param VehicleRecordRepository $vehicleRecordRepository Deletes the vehicle.
     * @param VehicleDeleteForm       $vehicleDeleteForm       The delete page.
     * @param StaffSeals              $staffSeals              Checks the form's envelope.
     * @param SoldVehicles            $soldVehicles            Whether a vehicle has a recorded sale.
     * @param StaffNotices            $staffNotices            Leaves the delete's notice for the list.
     */
    public function __construct(
        private VehicleRecords $vehicleRecords,
        private VehicleRecordRepository $vehicleRecordRepository,
        private VehicleDeleteForm $vehicleDeleteForm,
        private StaffSeals $staffSeals,
        private SoldVehicles $soldVehicles,
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a vehicle's delete page, with its session.
     *
     * @throws QueryException           When the vehicle cannot be read.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws LogicException           When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The page that asks first, or that says a sold vehicle stays; or 404.
     */
    public function confirm(ServerRequestInterface $serverRequest): PageResponse
    {
        $vehicle = $this->vehicleRecords->find($serverRequest);

        if (!$vehicle instanceof Vehicle) {
            return $this->vehicleRecords->missing();
        }

        return $this->vehicleDeleteForm->page($serverRequest, $vehicle, 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted delete form, with its session.
     *
     * @throws QueryException           When the vehicle cannot be read or deleted.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws LogicException           When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the list; the delete page again as 422 for a sold vehicle; 400 for an envelope that
     *                      is not this vehicle's; or 404.
     */
    public function delete(ServerRequestInterface $serverRequest): PageResponse
    {
        $vehicle = $this->vehicleRecords->find($serverRequest);

        if (!$vehicle instanceof Vehicle) {
            return $this->vehicleRecords->missing();
        }

        if (!$this->staffSeals->holds($serverRequest, $vehicle->path())) {
            return $this->vehicleRecords->refused();
        }

        if ($vehicle->status !== VehicleStatus::Sold && $this->deleted($vehicle)) {
            $this->staffNotices->keep($serverRequest, 'Deleted the ' . $vehicle->title() . ', VIN ' . $vehicle->vin . '.');

            return new PageRedirect(Vehicle::PAGES);
        }

        $latest = $this->vehicleRecords->find($serverRequest);

        return $latest instanceof Vehicle
            ? $this->vehicleDeleteForm->page($serverRequest, $latest, 422)
            : $this->vehicleRecords->missing();
    }

    /**
     * @param Vehicle $vehicle A vehicle that was not sold when it was read.
     *
     * @throws QueryException When it cannot be deleted for any reason but a sale recorded since it was read.
     *
     * @return bool Whether it was deleted; false when it is gone, sold meanwhile, or held by a sale's foreign key.
     */
    private function deleted(Vehicle $vehicle): bool
    {
        try {
            $deleted = $this->vehicleRecordRepository->delete($vehicle->vin);
        } catch (QueryException $queryException) {
            // A sale recorded after an edit's check can leave the vehicle unsold while its sale holds the row.
            if (!$this->soldVehicles->sale($vehicle->vin) instanceof VehicleSale) {
                throw $queryException;
            }

            $deleted = false;
        }

        return $deleted;
    }
}
