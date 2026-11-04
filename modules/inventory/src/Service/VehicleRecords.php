<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Domain\PageDocument;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use AIC\Tessera\Domain\TesseraRequest;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePanel;
use App\Inventory\Repository\VehicleRecordRepository;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffPanels;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use UnexpectedValueException;

use function array_key_exists;
use function is_string;
use function preg_match;

/**
 * The vehicle a `/app/inventory/{vin}` route names, its VIN checked as a VIN before any query, and its id; and the
 * pages for a vehicle that is not there (404), for a form refused for its envelope (400), and for a refused change to
 * one of its photos; and the panels the `VehiclePanel` plugins give its page (#31), as skeletons for a Tessera visit and
 * inline otherwise. Exported, so the panels' own routes find their vehicle, answer its 404 and frame their panel as
 * the vehicle's page does.
 */
final readonly class VehicleRecords
{
    /**
     * Reads a vehicle by its VIN.
     */
    private VehicleRecordRepository $vehicleRecordRepository;

    /**
     * @param EntityStore  $entityStore   The entity store, through which the vehicle is read.
     * @param StaffPanels  $staffPanels   The vehicle pages' frame.
     * @param ?StaffSeals  $staffSeals    Seals an inline panel's version; null where no panel is placed.
     * @param list<object> $vehiclePanels The `VehiclePanel` plugins, in tag order; empty where no panel is placed.
     */
    public function __construct(
        private EntityStore $entityStore,
        private StaffPanels $staffPanels,
        private ?StaffSeals $staffSeals = null,
        private array $vehiclePanels = [],
    ) {
        $this->vehicleRecordRepository = new VehicleRecordRepository($entityStore);
    }

    /**
     * @return VehicleRecordRepository One vehicle at a time, by VIN, for the forms that save and delete it.
     */
    public function repository(): VehicleRecordRepository
    {
        return $this->vehicleRecordRepository;
    }

    /**
     * @return StaffPanels The vehicle pages' frame, for a form that builds its page in it.
     */
    public function panels(): StaffPanels
    {
        return $this->staffPanels;
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a vehicle's route.
     *
     * @throws QueryException           When the vehicle cannot be read.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws InvalidArgumentException When an entity type it goes through is malformed.
     *
     * @return Vehicle|null The vehicle its `vin` names; null when that is no VIN or no vehicle has it.
     */
    public function find(ServerRequestInterface $serverRequest): ?Vehicle
    {
        $vin = $this->routeVin($serverRequest);

        return $vin === ''
            ? null
            : $this->vehicleRecordRepository->find($vin);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a vehicle's route.
     *
     * @throws QueryException           When the vehicle cannot be read.
     * @throws InvalidArgumentException When an entity type it goes through is malformed.
     *
     * @return int The id of the vehicle its `vin` names, which its photos refer to; 0 when that is no VIN or no vehicle
     *             has it.
     */
    public function vehicleId(ServerRequestInterface $serverRequest): int
    {
        $vin = $this->routeVin($serverRequest);

        return $vin === ''
            ? 0
            : $this->vehicleRecordRepository->vehicleId($vin);
    }

    /**
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument The page for a VIN no vehicle has, 404.
     */
    public function missing(): PageDocument
    {
        $message = 'No vehicle on the lot has that VIN. It may have been deleted.';

        return $this->staffPanels->warning('Vehicle not found', $message, 'vehicle-missing', 'Back to the list', Vehicle::PAGES, 404);
    }

    /**
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument The page for an edit or delete form whose envelope is missing, altered or another vehicle's,
     *                      400.
     */
    public function refused(): PageDocument
    {
        $message = 'This form was issued for another vehicle, or it changed on the way, so nothing was saved. Open the vehicle again.';

        return $this->staffPanels->warning('Form refused', $message, 'vehicle-refused', 'Back to the list', Vehicle::PAGES, 400);
    }

    /**
     * @param Vehicle $vehicle The vehicle a post was for.
     * @param string  $title   The page's title.
     * @param string  $message Why nothing was changed.
     * @param int     $status  403, 400 or 404.
     *
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument The page: the message as a warning, its id `vehicle-photo-{status}`, and a link back to the
     *                      vehicle; for a refused change to one of its photos (#16).
     */
    public function photoWarning(Vehicle $vehicle, string $title, string $message, int $status): PageDocument
    {
        return $this->staffPanels->warning($title, $message, 'vehicle-photo-' . $status, 'Back to the vehicle', $vehicle->path(), $status);
    }

    /**
     * @param ServerRequestInterface $serverRequest The vehicle page's request.
     * @param Vehicle                $vehicle       Its vehicle.
     *
     * @throws RuntimeException When a panel's version cannot be read.
     * @throws LogicException   When a panel's key, anchor or props do not fit.
     *
     * @return list<KeyedComponent> Each plugin's part, in tag order: its skeleton for a Tessera visit (a link, a
     *                              prefetch, Back and Forward, a redirect followed), which reads nothing, and the panel
     *                              inline for any other request, as with JavaScript off or a submit answered in place;
     *                              none without seals.
     */
    public function parts(ServerRequestInterface $serverRequest, Vehicle $vehicle): array
    {
        $staffSeals = $this->staffSeals;

        if (!$staffSeals instanceof StaffSeals) {
            return [];
        }

        $lazy = $serverRequest->getHeaderLine(TesseraRequest::REQUEST) === 'visit';
        $parts = [];

        foreach ($this->vehiclePanels as $vehiclePanel) {
            if (!($vehiclePanel instanceof VehiclePanel)) {
                continue;
            }

            $parts[] = $this->part($vehiclePanel, $staffSeals, $vehicle, $lazy);
        }

        return $parts;
    }

    /**
     * Asks one plugin through a parameter typed with the plugin type: natively an `instanceof` does not narrow an
     * `object` local (AIC #61), as `SoldVehicles::pluginSale()` says.
     *
     * @param VehiclePanel $vehiclePanel A panel plugin.
     * @param StaffSeals   $staffSeals   Seals its version.
     * @param Vehicle      $vehicle      The page's vehicle.
     * @param bool         $lazy         Whether to give its skeleton.
     *
     * @throws RuntimeException When its version cannot be read.
     * @throws LogicException   When its key, anchor or props do not fit.
     *
     * @return KeyedComponent Its part.
     */
    private function part(
        VehiclePanel $vehiclePanel,
        StaffSeals $staffSeals,
        Vehicle $vehicle,
        bool $lazy,
    ): KeyedComponent {
        return $vehiclePanel->part($this->entityStore, $staffSeals, $vehicle, $lazy);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a vehicle's route.
     *
     * @return string Its `vin` attribute when it is written in the VIN alphabet; empty otherwise.
     */
    private function routeVin(ServerRequestInterface $serverRequest): string
    {
        $attributes = $serverRequest->getAttributes();
        $vin = array_key_exists('vin', $attributes) && is_string($attributes['vin'])
            ? $attributes['vin']
            : '';

        return preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin) === 1
            ? $vin
            : '';
    }
}
