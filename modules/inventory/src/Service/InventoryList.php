<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\SourcedComponent;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Form\VehicleRowForm;
use App\Inventory\Repository\VehicleRepository;
use App\Staff\Service\StaffNotices;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The inventory list's panel: the notice a delete left, a link to add a vehicle, the filter bar and the results of the
 * page asked for, anchored `inventory-results` for the controls that refresh them. The results come from their source,
 * `InventoryResults`, which reads the vehicles only when the results are rendered (#14), so a refresh of them alone
 * reads the makes the query is checked against and their own vehicles, and nothing more. A row's own answer (#28)
 * builds the panel under the same key, title and results' key, holding that row alone.
 */
final readonly class InventoryList
{
    /**
     * The panel's key on the page, and its id.
     */
    public const string KEY = 'vehicles';

    /**
     * The panel's title.
     */
    public const string TITLE = 'Vehicles on the lot';

    /**
     * The panel's line under its title.
     */
    public const string LINE = 'Search, filter, sort and page the stock';

    /**
     * The results' key in the panel.
     */
    public const string RESULTS = 'table';

    /**
     * @param VehicleRepository $vehicleRepository The vehicles.
     * @param VehicleRowForm    $vehicleRowForm    Each vehicle's row.
     * @param InventoryQueries  $inventoryQueries  Reads what the list is asked for.
     * @param InventoryFilters  $inventoryFilters  The filter bar.
     * @param InventoryTable    $inventoryTable    The table and the pages.
     * @param StaffNotices      $staffNotices      The last delete's notice.
     */
    public function __construct(
        private VehicleRepository $vehicleRepository,
        private VehicleRowForm $vehicleRowForm,
        private InventoryQueries $inventoryQueries = new InventoryQueries(),
        private InventoryFilters $inventoryFilters = new InventoryFilters(),
        private InventoryTable $inventoryTable = new InventoryTable(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request, with its query parameters.
     *
     * @throws QueryException When the makes cannot be read.
     * @throws LogicException When the request carries no session, or a key is malformed.
     *
     * @return Panel The list, in a wide panel.
     */
    public function panel(ServerRequestInterface $serverRequest): Panel
    {
        $makes = $this->vehicleRepository->makes();
        $inventoryQuery = $this->inventoryQueries->read($serverRequest, $makes);
        $body = [];

        foreach ($this->staffNotices->take($serverRequest, 'inventory-notice') as $key => $notice) {
            $body[] = new KeyedComponent($key, $notice);
        }

        $body[] = new KeyedComponent('add', new Link('Add a vehicle', Vehicle::PAGES . '/new'));
        $body[] = new KeyedComponent('filters', $this->inventoryFilters->filterBar($inventoryQuery, $makes));
        $inventoryResults = new InventoryResults($this->vehicleRepository, $this->inventoryTable, $inventoryQuery, $this->vehicleRowForm);
        $body[] = new KeyedComponent(self::RESULTS, new SourcedComponent($inventoryResults), '', InventoryTable::ANCHOR);

        return new Panel(self::KEY, self::TITLE, self::LINE, true, $body);
    }
}
