<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Form\InventoryQuery;
use App\Inventory\Form\VehicleRowForm;
use App\Inventory\Repository\VehicleRepository;
use LogicException;
use Override;
use UnexpectedValueException;

use function intdiv;

/**
 * The inventory list's results as a source (#14, AIC #192): it holds the repository and the query the list was asked
 * for, and reads only in `build()`, which the render pass calls when it renders the results. A Tessera visit that
 * names the results alone (`@inventory-results`) builds them and nothing else's data. A page past the last shows the
 * first. Each row is `VehicleRowForm`'s, the shape a row's own answer has (#28).
 */
final readonly class InventoryResults implements ComponentSource
{
    /**
     * The vehicles on a page.
     */
    public const int PAGE = 15;

    /**
     * @param VehicleRepository $vehicleRepository The vehicles.
     * @param InventoryTable    $inventoryTable    The table and the pages.
     * @param InventoryQuery    $inventoryQuery    What the list was asked for.
     * @param VehicleRowForm    $vehicleRowForm    Each vehicle's row.
     */
    public function __construct(
        private VehicleRepository $vehicleRepository,
        private InventoryTable $inventoryTable,
        private InventoryQuery $inventoryQuery,
        private VehicleRowForm $vehicleRowForm,
    ) {
    }

    /**
     * @throws QueryException           When the vehicles cannot be read.
     * @throws UnexpectedValueException When a stored status is no status.
     * @throws LogicException           When a key is malformed; or a vehicle's path cannot be sealed as its row's props.
     *
     * @return Component The sort the filter form sends, the page's table and, past one page, the pages.
     */
    #[Override]
    public function build(): Component
    {
        $count = $this->vehicleRepository->count($this->inventoryQuery);
        $pages = intdiv($count + self::PAGE - 1, self::PAGE);
        $page = $this->inventoryQuery->page <= $pages
            ? $this->inventoryQuery->page
            : 1;
        $rows = [];

        foreach ($this->vehicleRepository->page($this->inventoryQuery, self::PAGE, ($page - 1) * self::PAGE) as $vehicle) {
            $rows[] = $this->vehicleRowForm->shown($vehicle);
        }

        return $this->inventoryTable->results($this->inventoryQuery, $rows, $count, $page, $pages);
    }
}
