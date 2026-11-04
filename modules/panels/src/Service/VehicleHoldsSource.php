<?php

declare(strict_types=1);

namespace App\Panels\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Holds\Repository\HoldRepository;
use App\Inventory\Entity\Vehicle;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;
use Override;

/**
 * A vehicle's holds as a source (#31, AIC #192), the second table of its leads and holds panel: it holds holds'
 * exported repository and the vehicle, and reads only in `build()`: the newest ten, whatever their status, with their
 * customer, who placed them, their state and when they expire.
 */
final readonly class VehicleHoldsSource implements ComponentSource
{
    /**
     * The most holds it lists.
     */
    public const int SHOWN = 10;

    /**
     * @param HoldRepository $holdRepository The holds.
     * @param Vehicle        $vehicle        The vehicle.
     */
    public function __construct(
        private HoldRepository $holdRepository,
        private Vehicle $vehicle,
    ) {
    }

    /**
     * @throws QueryException           When the holds cannot be read.
     * @throws InvalidArgumentException When the entity type or a row's key is malformed.
     *
     * @return Component The holds' table.
     */
    #[Override]
    public function build(): Component
    {
        $rows = [];

        foreach ($this->holdRepository->forVehicle($this->vehicle->vin, self::SHOWN) as $index => $holdTrace) {
            $rows[] = new KeyedComponent('hold-' . $index, new TableRow([
                new TableCell($holdTrace->placedOn, '', true, false, null),
                new TableCell($holdTrace->customerName === '' ? 'No customer' : $holdTrace->customerName, '', false, false, null),
                new TableCell($holdTrace->placerName === '' ? 'No one' : $holdTrace->placerName, '', false, false, null),
                new TableCell($holdTrace->state, '', false, false, null),
                new TableCell($holdTrace->expires, '', true, false, null),
            ]));
        }

        return new DataTable('activity-' . $this->vehicle->vin . '-holds', 'Holds, newest first.', 'No holds on this vehicle.', 'content', [
            new TableColumn('Placed', '', 'none', '', false),
            new TableColumn('Customer', '', 'none', '', false),
            new TableColumn('Placed by', '', 'none', '', false),
            new TableColumn('State', '', 'none', '', false),
            new TableColumn('Expires', '', 'none', '', false),
        ], $rows);
    }
}
