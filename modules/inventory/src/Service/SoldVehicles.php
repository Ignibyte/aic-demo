<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleSale;
use App\Inventory\Entity\VehicleStatus;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;
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
     * @param EntityStore  $entityStore  The entity store, handed to each plugin.
     * @param list<object> $vehicleSales The `VehicleSales` plugins, in tag order (`#[Tagged]`).
     */
    public function __construct(
        private EntityStore $entityStore,
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

            $vehicleSale = $this->pluginSale($plugin, $vin);

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
                return $this->pluginPath($plugin, $vehicle->vin);
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

    /**
     * Asks one plugin through a parameter typed with the plugin type: natively an `instanceof` does not narrow an
     * `object` local, so a call on it dispatches dynamically and what it returns is never released (AIC #61,
     * framework/docs/native.md, "Write code with concrete types").
     *
     * @param VehicleSales $vehicleSales A plugin.
     * @param string       $vin          A vehicle's VIN.
     *
     * @throws QueryException           When the plugin cannot read the sale.
     * @throws InvalidArgumentException When an entity type the plugin reads through is malformed.
     *
     * @return VehicleSale|null The plugin's sale of the vehicle; null when it has none.
     */
    private function pluginSale(VehicleSales $vehicleSales, string $vin): ?VehicleSale
    {
        return $vehicleSales->sale($this->entityStore, $vin);
    }

    /**
     * Asks one plugin through a parameter typed with the plugin type, as `pluginSale()` does.
     *
     * @param VehicleSales $vehicleSales A plugin.
     * @param string       $vin          A vehicle's VIN.
     *
     * @return string The plugin's path to the form that records the vehicle's sale.
     */
    private function pluginPath(VehicleSales $vehicleSales, string $vin): string
    {
        return $vehicleSales->recordPath($vin);
    }
}
