<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Repository\VehicleRepository;
use App\Theme\Components\Panel\Panel;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function intdiv;

/**
 * The inventory list's panel: the filter bar, the table of the page asked for (15 vehicles a page; a page past the
 * last shows the first) and, past one page, the pages.
 */
final readonly class InventoryList
{
    /**
     * The vehicles on a page.
     */
    private const int PAGE = 15;

    /**
     * @param VehicleRepository $vehicleRepository The vehicles.
     * @param InventoryQueries  $inventoryQueries  Reads what the list is asked for.
     * @param InventoryFilters  $inventoryFilters  The filter bar.
     * @param InventoryTable    $inventoryTable    The table and the pages.
     */
    public function __construct(
        private VehicleRepository $vehicleRepository,
        private InventoryQueries $inventoryQueries = new InventoryQueries(),
        private InventoryFilters $inventoryFilters = new InventoryFilters(),
        private InventoryTable $inventoryTable = new InventoryTable(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request, with its query parameters.
     *
     * @throws QueryException           When the vehicles cannot be read.
     * @throws UnexpectedValueException When a stored status is no status.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Panel The list, in a wide panel.
     */
    public function panel(ServerRequestInterface $serverRequest): Panel
    {
        $makes = $this->vehicleRepository->makes();
        $inventoryQuery = $this->inventoryQueries->read($serverRequest, $makes);
        $count = $this->vehicleRepository->count($inventoryQuery);
        $pages = intdiv($count + self::PAGE - 1, self::PAGE);
        $page = $inventoryQuery->page <= $pages
            ? $inventoryQuery->page
            : 1;
        $vehicles = $this->vehicleRepository->page($inventoryQuery, self::PAGE, ($page - 1) * self::PAGE);
        $body = [
            new KeyedComponent('filters', $this->inventoryFilters->filterBar($inventoryQuery, $makes)),
            new KeyedComponent('table', $this->inventoryTable->table($inventoryQuery, $vehicles, $count, $page, $pages)),
        ];

        if ($pages > 1) {
            $body[] = new KeyedComponent('pages', $this->inventoryTable->pagination($inventoryQuery, $page, $pages));
        }

        return new Panel('vehicles', 'Vehicles on the lot', 'Search, filter, sort and page the stock', true, $body);
    }
}
