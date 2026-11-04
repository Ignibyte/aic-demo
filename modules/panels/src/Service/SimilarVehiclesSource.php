<?php

declare(strict_types=1);

namespace App\Panels\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Repository\VehicleRepository;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;
use Override;
use UnexpectedValueException;

use function number_format;
use function trim;

/**
 * The vehicles like one as a source (#31, AIC #192): it holds the vehicles' repository and the vehicle, and reads only
 * in `build()`: at most four not sold, of its make and body style, nearest its list price, as a table whose VINs link
 * to their pages.
 */
final readonly class SimilarVehiclesSource implements ComponentSource
{
    /**
     * The most vehicles it lists.
     */
    public const int SHOWN = 4;

    /**
     * @param VehicleRepository $vehicleRepository The vehicles.
     * @param Vehicle           $vehicle           The vehicle the others are like.
     */
    public function __construct(
        private VehicleRepository $vehicleRepository,
        private Vehicle $vehicle,
    ) {
    }

    /**
     * @throws QueryException           When the vehicles cannot be read.
     * @throws UnexpectedValueException When a stored status is no status.
     * @throws InvalidArgumentException When the entity type or a row's key is malformed.
     *
     * @return Component The table.
     */
    #[Override]
    public function build(): Component
    {
        $rows = [];

        foreach ($this->vehicleRepository->similar($this->vehicle, self::SHOWN) as $vehicle) {
            $rows[] = $this->row($vehicle);
        }

        $summary = 'Other ' . $this->vehicle->make . ' ' . $this->vehicle->bodyStyle . ' vehicles not sold, nearest its list price first.';

        return new DataTable('similar-' . $this->vehicle->vin . '-table', $summary, 'No similar vehicles on the lot.', 'content', [
            new TableColumn('VIN', '', 'none', '', false),
            new TableColumn('Year', '', 'none', '', false),
            new TableColumn('Model', '', 'none', '', false),
            new TableColumn('Mileage', '', 'none', '', true),
            new TableColumn('List price', '', 'none', '', true),
            new TableColumn('Status', '', 'none', '', false),
        ], $rows);
    }

    /**
     * @param Vehicle $vehicle A vehicle like the page's.
     *
     * @throws InvalidArgumentException When its VIN does not fit a key, which a stored VIN always does.
     *
     * @return KeyedComponent Its row, keyed by its VIN, its VIN linking to its page.
     */
    private function row(Vehicle $vehicle): KeyedComponent
    {
        return new KeyedComponent($vehicle->vin, new TableRow([
            new TableCell($vehicle->vin, $vehicle->path(), true, false, null),
            new TableCell((string) $vehicle->year, '', false, false, null),
            new TableCell(trim($vehicle->model . ' ' . $vehicle->trim), '', false, false, null),
            new TableCell(number_format($vehicle->mileage) . ' mi', '', false, true, null),
            new TableCell('$' . number_format($vehicle->price->list), '', false, true, null),
            new TableCell($vehicle->status->label(), '', false, false, null),
        ]));
    }
}
