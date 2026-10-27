<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Controller\Domain\PageDocument;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Repository\VehicleRecordRepository;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function array_key_exists;
use function is_string;
use function preg_match;

/**
 * The vehicle a `/app/inventory/{vin}` route names, its VIN checked as a VIN before any query; and the pages for a
 * vehicle that is not there (404) and for a form refused for its envelope (400).
 */
final readonly class VehicleRecords
{
    /**
     * @param VehicleRecordRepository $vehicleRecordRepository Reads a vehicle by its VIN.
     * @param VehiclePanels           $vehiclePanels           The vehicle pages' frame.
     */
    public function __construct(
        private VehicleRecordRepository $vehicleRecordRepository,
        private VehiclePanels $vehiclePanels,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a vehicle's route.
     *
     * @throws QueryException           When the vehicle cannot be read.
     * @throws UnexpectedValueException When its stored status is no status.
     *
     * @return Vehicle|null The vehicle its `vin` names; null when that is no VIN or no vehicle has it.
     */
    public function find(ServerRequestInterface $serverRequest): ?Vehicle
    {
        $vin = $this->vin($serverRequest);

        if (preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin) !== 1) {
            return null;
        }

        return $this->vehicleRecordRepository->find($vin);
    }

    /**
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument The page for a VIN no vehicle has, 404.
     */
    public function missing(): PageDocument
    {
        $message = 'No vehicle on the lot has that VIN. It may have been deleted.';

        return $this->vehiclePanels->warning('Vehicle not found', $message, 'vehicle-missing', 'Back to the list', Vehicle::PAGES, 404);
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

        return $this->vehiclePanels->warning('Form refused', $message, 'vehicle-refused', 'Back to the list', Vehicle::PAGES, 400);
    }

    /**
     * Reads the attribute as `RequestSession` does, through a native `string` return: elephc does not narrow an
     * untyped value by `is_string()` and refuses `preg_match()` on it, as gate:23 found. No AIC ticket carries this
     * yet.
     *
     * @param ServerRequestInterface $serverRequest A request to a vehicle's route.
     *
     * @return string Its `vin` attribute; empty when it is not a string.
     */
    private function vin(ServerRequestInterface $serverRequest): string
    {
        $attributes = $serverRequest->getAttributes();

        return array_key_exists('vin', $attributes) && is_string($attributes['vin'])
            ? $attributes['vin']
            : '';
    }
}
