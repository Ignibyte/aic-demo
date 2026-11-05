<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use App\Brochures\Service\Brochures;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Service\VehicleRecords;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffNotices;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * The vehicle form's flows: the blank form, a new vehicle checked and made, a stored one's form, and that form checked
 * and saved. A refusal shows the form again as 422 with what was typed; a save queues the car's brochure (#44), leaves a
 * notice and goes to the vehicle (303).
 */
final readonly class VehicleSaves
{
    /**
     * The form's page.
     */
    private VehicleForm $vehicleForm;

    /**
     * @param VehicleRecords $vehicleRecords The route's vehicle, its repository, which saves it, and the 404 and 400 pages.
     * @param VehicleChecks  $vehicleChecks  Checks the form.
     * @param StaffSeals     $staffSeals     Seals and checks an edit form's envelope.
     * @param Brochures      $brochures      Queues the car's brochure after a save (#44).
     * @param VehicleEntries $vehicleEntries Fills the form.
     * @param StaffNotices   $staffNotices   Leaves the save's notice.
     */
    public function __construct(
        private VehicleRecords $vehicleRecords,
        private VehicleChecks $vehicleChecks,
        private StaffSeals $staffSeals,
        private Brochures $brochures,
        private VehicleEntries $vehicleEntries = new VehicleEntries(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
        $this->vehicleForm = new VehicleForm($vehicleRecords->panels(), $staffSeals);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for the new vehicle form, with its session.
     *
     * @throws QueryException When the database's date cannot be read.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse The blank form: in stock, on the lot from the database's today.
     */
    public function start(ServerRequestInterface $serverRequest): PageResponse
    {
        $vehicleEntry = $this->vehicleEntries->blank($this->vehicleRecords->repository()->today());

        return $this->vehicleForm->page($serverRequest, $vehicleEntry, [], '', 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted new vehicle, with its session.
     *
     * @throws QueryException           When the vehicles cannot be read or written, or the brochure's job cannot be queued.
     * @throws UnexpectedValueException When the checked status is no status.
     * @throws LogicException           When the request has no session, an envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the new vehicle, or the form again as 422.
     */
    public function create(ServerRequestInterface $serverRequest): PageResponse
    {
        $vehicleEntry = $this->vehicleEntries->posted($serverRequest);
        $errors = $this->vehicleChecks->errors($vehicleEntry, '', $this->vehicleRecords->repository()->today());

        if ($errors !== []) {
            return $this->vehicleForm->page($serverRequest, $vehicleEntry, $errors, '', 422);
        }

        $vehicle = $this->vehicleChecks->vehicle($vehicleEntry);

        if (!$this->vehicleRecords->repository()->create($vehicle)) {
            return $this->vehicleForm->page($serverRequest, $vehicleEntry, ['vin' => VehicleChecks::VIN_TAKEN], '', 422);
        }

        return $this->saved($serverRequest, $vehicle, 'Added this vehicle.');
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a vehicle's edit form, with its session.
     *
     * @throws QueryException           When the vehicle cannot be read.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws LogicException           When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The form filled from the vehicle, or 404.
     */
    public function edit(ServerRequestInterface $serverRequest): PageResponse
    {
        $vehicle = $this->vehicleRecords->find($serverRequest);

        if (!$vehicle instanceof Vehicle) {
            return $this->vehicleRecords->missing();
        }

        return $this->vehicleForm->page($serverRequest, $this->vehicleEntries->filled($vehicle), [], $vehicle->vin, 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted edit form, with its session.
     *
     * @throws QueryException           When the vehicles cannot be read or written, or the brochure's job cannot be queued.
     * @throws UnexpectedValueException When a status is no status.
     * @throws LogicException           When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the vehicle's path, a new one when its VIN changed; the form again as 422; 400 for
     *                      an envelope that is not this vehicle's; or 404.
     */
    public function update(ServerRequestInterface $serverRequest): PageResponse
    {
        $current = $this->vehicleRecords->find($serverRequest);

        if (!$current instanceof Vehicle) {
            return $this->vehicleRecords->missing();
        }

        $path = $current->path();

        if (!$this->staffSeals->holds($serverRequest, $path)) {
            return $this->vehicleRecords->refused();
        }

        $vehicleEntry = $this->vehicleEntries->posted($serverRequest);
        $errors = $this->vehicleChecks->errors($vehicleEntry, $current->vin, $this->vehicleRecords->repository()->today());

        if ($errors !== []) {
            return $this->vehicleForm->page($serverRequest, $vehicleEntry, $errors, $current->vin, 422);
        }

        return $this->changed($serverRequest, $current, $vehicleEntry);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted edit form, with its session.
     * @param Vehicle                $current       The vehicle as stored.
     * @param VehicleEntry           $vehicleEntry  What was posted, found valid.
     *
     * @throws QueryException           When the vehicle cannot be written for any reason but a VIN taken meanwhile, or
     *                                  the brochure's job cannot be queued.
     * @throws UnexpectedValueException When the checked status is no status.
     * @throws LogicException           When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the vehicle; the form again as 422 when another save took the VIN since the check;
     *                      or 404 when the vehicle went meanwhile.
     */
    private function changed(
        ServerRequestInterface $serverRequest,
        Vehicle $current,
        VehicleEntry $vehicleEntry,
    ): PageResponse {
        $vehicle = $this->vehicleChecks->vehicle($vehicleEntry);

        try {
            $updated = $this->vehicleRecords->repository()->update($current->vin, $vehicle);
        } catch (QueryException $queryException) {
            if (!$this->vehicleRecords->repository()->taken($vehicle->vin, $current->vin)) {
                throw $queryException;
            }

            return $this->vehicleForm->page($serverRequest, $vehicleEntry, ['vin' => VehicleChecks::VIN_TAKEN], $current->vin, 422);
        }

        if (!$updated) {
            return $this->vehicleRecords->missing();
        }

        return $this->saved($serverRequest, $vehicle, 'Saved your changes.');
    }

    /**
     * Queues the car's brochure (#44), which the `car-brochure` job writes from it as saved, or deletes when it is not
     * for sale, then leaves the notice.
     *
     * @param ServerRequestInterface $serverRequest The post that saved, with its session.
     * @param Vehicle                $vehicle       The vehicle as saved.
     * @param string                 $message       The notice its page shows.
     *
     * @throws LogicException When the request carries no session.
     * @throws QueryException When the vehicle's id cannot be read or the brochure's job cannot be queued.
     *
     * @return PageRedirect 303 to the vehicle.
     */
    private function saved(ServerRequestInterface $serverRequest, Vehicle $vehicle, string $message): PageRedirect
    {
        $this->brochures->queue($this->vehicleRecords->repository()->vehicleId($vehicle->vin));
        $this->staffNotices->keep($serverRequest, $message);

        return new PageRedirect($vehicle->path());
    }
}
