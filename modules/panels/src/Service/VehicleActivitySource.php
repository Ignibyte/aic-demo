<?php

declare(strict_types=1);

namespace App\Panels\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Leads\Repository\VehicleLeadRepository;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;
use Override;

/**
 * A vehicle's leads as a source (#31, AIC #192), the first table of its leads and holds panel: it holds leads'
 * exported repository and the vehicle, and reads only in `build()`: the newest ten, each linking to its lead's page,
 * with its kind, stage and salesperson. `VehicleHoldsSource` gives the panel's holds.
 */
final readonly class VehicleActivitySource implements ComponentSource
{
    /**
     * The most leads it lists.
     */
    public const int SHOWN = 10;

    /**
     * @param VehicleLeadRepository $vehicleLeadRepository The vehicle's leads.
     * @param Vehicle               $vehicle               The vehicle.
     */
    public function __construct(
        private VehicleLeadRepository $vehicleLeadRepository,
        private Vehicle $vehicle,
    ) {
    }

    /**
     * @throws QueryException           When the leads cannot be read.
     * @throws InvalidArgumentException When a type or a row's key is malformed.
     *
     * @return Component The leads' table.
     */
    #[Override]
    public function build(): Component
    {
        $rows = [];

        foreach ($this->vehicleLeadRepository->forVehicle($this->vehicle->vin, self::SHOWN) as $vehicleLead) {
            $seller = $vehicleLead->seller === ''
                ? 'Unassigned'
                : $vehicleLead->seller;
            $rows[] = new KeyedComponent('lead-' . $vehicleLead->id, new TableRow([
                new TableCell($vehicleLead->receivedOn, '', true, false, null),
                new TableCell($vehicleLead->name, $vehicleLead->path(), false, false, null),
                new TableCell($vehicleLead->kind, '', false, false, null),
                new TableCell($vehicleLead->stage, '', false, false, null),
                new TableCell($seller, '', false, false, null),
            ]));
        }

        return new DataTable('activity-' . $this->vehicle->vin . '-leads', 'Leads, newest first.', 'No leads on this vehicle.', 'content', [
            new TableColumn('Came in', '', 'none', '', false),
            new TableColumn('Visitor', '', 'none', '', false),
            new TableColumn('Kind', '', 'none', '', false),
            new TableColumn('Stage', '', 'none', '', false),
            new TableColumn('Salesperson', '', 'none', '', false),
        ], $rows);
    }
}
