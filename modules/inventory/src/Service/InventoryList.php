<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Repository\VehicleRepository;
use App\Staff\Service\StaffNotices;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function intdiv;

/**
 * The inventory list's panel: the notice a delete left, a link to add a vehicle, the filter bar and the results of the
 * page asked for (15 vehicles a page; a page past the last shows the first): the table and, past one page, the pages,
 * anchored `inventory-results` for the controls that refresh them.
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
     * @param StaffNotices      $staffNotices      The last delete's notice.
     */
    public function __construct(
        private VehicleRepository $vehicleRepository,
        private InventoryQueries $inventoryQueries = new InventoryQueries(),
        private InventoryFilters $inventoryFilters = new InventoryFilters(),
        private InventoryTable $inventoryTable = new InventoryTable(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request, with its query parameters.
     *
     * @throws QueryException           When the vehicles cannot be read.
     * @throws UnexpectedValueException When a stored status is no status.
     * @throws LogicException           When the request carries no session, or a key is malformed.
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
        $body = [];

        foreach ($this->staffNotices->take($serverRequest, 'inventory-notice') as $key => $notice) {
            $body[] = new KeyedComponent($key, $notice);
        }

        $body[] = new KeyedComponent('add', new Link('Add a vehicle', Vehicle::PAGES . '/new'));
        $body[] = new KeyedComponent('filters', $this->inventoryFilters->filterBar($inventoryQuery, $makes));
        $results = $this->inventoryTable->results($inventoryQuery, $vehicles, $count, $page, $pages);
        $body[] = new KeyedComponent('table', $results, '', InventoryTable::ANCHOR);

        return new Panel('vehicles', 'Vehicles on the lot', 'Search, filter, sort and page the stock', true, $body);
    }
}
