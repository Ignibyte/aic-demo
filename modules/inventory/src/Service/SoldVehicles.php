<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleSale;
use App\Inventory\Entity\VehicleStatus;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use LogicException;

use function number_format;

/**
 * Asks the `VehicleSales` plugins about a vehicle: its sale, the first plugin's that has one, and where its sale is
 * recorded; and builds what the vehicle's page shows of them: the sale in a one-row table under the card, or, for a
 * vehicle that is neither sold nor has a sale, the path behind "Record a sale". With no plugin a vehicle has no sale
 * and no path to record one, and its page shows neither.
 */
final readonly class SoldVehicles
{
    /**
     * @param Database     $database     The database, handed to each plugin.
     * @param list<object> $vehicleSales The `VehicleSales` plugins, in tag order (`#[Tagged]`).
     */
    public function __construct(
        private Database $database,
        private array $vehicleSales,
    ) {
    }

    /**
     * @param string $vin A vehicle's VIN.
     *
     * @throws QueryException When a plugin cannot read the sale.
     *
     * @return VehicleSale|null Its recorded sale; null when no plugin has one.
     */
    public function sale(string $vin): ?VehicleSale
    {
        foreach ($this->vehicleSales as $plugin) {
            if (!($plugin instanceof VehicleSales)) {
                continue;
            }

            $vehicleSale = $plugin->sale($this->database, $vin);

            if ($vehicleSale instanceof VehicleSale) {
                return $vehicleSale;
            }
        }

        return null;
    }

    /**
     * @param Vehicle          $vehicle     A vehicle.
     * @param VehicleSale|null $vehicleSale Its recorded sale; null when it has none.
     *
     * @return string The first plugin's path to record its sale; empty when it is sold, has a sale, or there is no
     *                plugin.
     */
    public function recordPath(Vehicle $vehicle, ?VehicleSale $vehicleSale): string
    {
        if ($vehicleSale instanceof VehicleSale || $vehicle->status === VehicleStatus::Sold) {
            return '';
        }

        foreach ($this->vehicleSales as $plugin) {
            if ($plugin instanceof VehicleSales) {
                return $plugin->recordPath($vehicle->vin);
            }
        }

        return '';
    }

    /**
     * @param VehicleSale|null $vehicleSale A vehicle's recorded sale; null when it has none.
     *
     * @throws LogicException When a key is malformed.
     *
     * @return array<string, DataTable> The sale's table under the key `sale`; empty when there is no sale.
     */
    public function tables(?VehicleSale $vehicleSale): array
    {
        if (!$vehicleSale instanceof VehicleSale) {
            return [];
        }

        $tableRow = new TableRow([
            new TableCell($vehicleSale->soldOn, $vehicleSale->path, false, false, null),
            new TableCell($vehicleSale->customer, $vehicleSale->customerPath, false, false, null),
            new TableCell($vehicleSale->salesperson, '', false, false, null),
            new TableCell('$' . number_format($vehicleSale->price), '', false, true, null),
        ]);

        return ['sale' => new DataTable('vehicle-sale', 'Sale', 'No sale recorded.', 'content', [
            new TableColumn('Sold on', '', 'none', '', false),
            new TableColumn('Customer', '', 'none', '', false),
            new TableColumn('Sold by', '', 'none', '', false),
            new TableColumn('Price', '', 'none', '', true),
        ], [new KeyedComponent('sale', $tableRow)])];
    }
}
