<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Form\InventoryQuery;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\FormValue\FormValue;
use App\Theme\Components\Pagination\PageLink;
use App\Theme\Components\Pagination\Pagination;
use App\Theme\Components\TableResults\TableResults;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;

use function number_format;

/**
 * The inventory list's results: a row per vehicle on the page, heads that sort (a second click on the sorted one
 * reverses it), a link per page, each link keeping the search and filters, and the sort the filter form sends. Tessera
 * answers a filter, a sort or a page with them alone.
 */
final readonly class InventoryTable
{
    /**
     * The results' Tessera path: the `content` region, the staff pages' unkeyed grid in it (`c0`), the grid's panel
     * `vehicles` and the panel's part `table`, each loop's key as the render pass writes it.
     */
    public const string TARGET = 'content/c0/PanelGrid.1:vehicles/Panel.1:table';

    /**
     * @param InventoryLinks  $inventoryLinks  Writes the links.
     * @param VehicleStatuses $vehicleStatuses Gives each status its badge colour.
     */
    public function __construct(
        private InventoryLinks $inventoryLinks = new InventoryLinks(),
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
    ) {
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     * @param list<Vehicle>  $vehicles       The page's vehicles.
     * @param int            $count          How many vehicles match.
     * @param int            $page           The page shown.
     * @param int            $pages          How many pages there are.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return TableResults The sort the filter form sends, the table and, past one page, the pages.
     */
    public function results(
        InventoryQuery $inventoryQuery,
        array $vehicles,
        int $count,
        int $page,
        int $pages,
    ): TableResults {
        $sort = $this->inventoryLinks->sortValue($inventoryQuery->sort, $inventoryQuery->descending);
        $parts = [
            new KeyedComponent('sort', new FormValue(InventoryFilters::FORM, 'sort', $sort)),
            new KeyedComponent('table', $this->table($inventoryQuery, $vehicles, $count, $page, $pages)),
        ];

        if ($pages > 1) {
            $parts[] = new KeyedComponent('pages', $this->pagination($inventoryQuery, $page, $pages));
        }

        return new TableResults($parts);
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     * @param list<Vehicle>  $vehicles       The page's vehicles.
     * @param int            $count          How many vehicles match.
     * @param int            $page           The page shown.
     * @param int            $pages          How many pages there are.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return DataTable The page's rows under the heads, or the empty line.
     */
    private function table(
        InventoryQuery $inventoryQuery,
        array $vehicles,
        int $count,
        int $page,
        int $pages,
    ): DataTable {
        $rows = [];

        foreach ($vehicles as $vehicle) {
            $rows[] = new KeyedComponent($vehicle->vin, $this->row($vehicle));
        }

        $summary = $this->summary($count, $page, $pages);
        $empty = 'No vehicle matches. Try fewer filters, or clear them.';

        return new DataTable('vehicles-table', $summary, $empty, self::TARGET, $this->columns($inventoryQuery), $rows);
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     * @param int            $page           The page shown.
     * @param int            $pages          How many pages there are.
     *
     * @return Pagination A link per page, keeping the search, the filters and the sort.
     */
    private function pagination(InventoryQuery $inventoryQuery, int $page, int $pages): Pagination
    {
        $links = [];

        for ($number = 1; $number <= $pages; $number += 1) {
            $href = $this->inventoryLinks->href($inventoryQuery, $inventoryQuery->sort, $inventoryQuery->descending, $number);
            $links[] = new PageLink((string) $number, $href, $number === $page);
        }

        return new Pagination('vehicles-pages', self::TARGET, $links);
    }

    /**
     * @param Vehicle $vehicle A vehicle.
     *
     * @return TableRow Its row: the VIN, linked to its page, what it is, its mileage and price, its status as a badge and
     *                  the day it came.
     */
    private function row(Vehicle $vehicle): TableRow
    {
        $status = $vehicle->status;

        return new TableRow([
            new TableCell($vehicle->vin, $vehicle->path(), true, false, null),
            new TableCell((string) $vehicle->year, '', false, false, null),
            new TableCell($vehicle->make, '', false, false, null),
            new TableCell($vehicle->model . ' ' . $vehicle->trim, '', false, false, null),
            new TableCell($vehicle->bodyStyle, '', false, false, null),
            new TableCell($vehicle->exteriorColour, '', false, false, null),
            new TableCell(number_format($vehicle->mileage) . ' mi', '', false, true, null),
            new TableCell('$' . number_format($vehicle->price->list), '', false, true, null),
            new TableCell($status->label(), '', false, false, $this->vehicleStatuses->tone($status)),
            new TableCell($vehicle->addedOn, '', false, false, null),
        ]);
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     *
     * @return list<TableColumn> The heads; all but VIN, Body and Colour sort.
     */
    private function columns(InventoryQuery $inventoryQuery): array
    {
        return [
            new TableColumn('VIN', '', 'none', '', false),
            $this->sortable('Year', 'year', $inventoryQuery, false),
            $this->sortable('Make', 'make', $inventoryQuery, false),
            $this->sortable('Model', 'model', $inventoryQuery, false),
            new TableColumn('Body', '', 'none', '', false),
            new TableColumn('Colour', '', 'none', '', false),
            $this->sortable('Mileage', 'mileage', $inventoryQuery, true),
            $this->sortable('Price', 'price', $inventoryQuery, true),
            $this->sortable('Status', 'status', $inventoryQuery, false),
            $this->sortable('Added', 'added', $inventoryQuery, false),
        ];
    }

    /**
     * @param string         $label          The head's text.
     * @param string         $sort           The column it sorts by.
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     * @param bool           $numeric        Whether its cells are numbers.
     *
     * @return TableColumn The head: when it is the sort, its direction and a link to the other one; else a link to it
     *                     ascending. Either link starts again at page 1.
     */
    private function sortable(
        string $label,
        string $sort,
        InventoryQuery $inventoryQuery,
        bool $numeric,
    ): TableColumn {
        if ($inventoryQuery->sort !== $sort) {
            return new TableColumn($label, $this->inventoryLinks->href($inventoryQuery, $sort, false, 1), 'none', '', $numeric);
        }

        $descending = $inventoryQuery->descending;
        $href = $this->inventoryLinks->href($inventoryQuery, $sort, !$descending, 1);

        return new TableColumn($label, $href, $descending ? 'descending' : 'ascending', $descending ? ' ▼' : ' ▲', $numeric);
    }

    /**
     * @param int $count How many vehicles match.
     * @param int $page  The page shown.
     * @param int $pages How many pages there are.
     *
     * @return string What the table shows: the count, and which page when there is more than one.
     */
    private function summary(int $count, int $page, int $pages): string
    {
        $noun = $count === 1
            ? 'vehicle'
            : 'vehicles';

        if ($pages < 2) {
            return number_format($count) . ' ' . $noun;
        }

        return number_format($count) . ' ' . $noun . ', page ' . $page . ' of ' . $pages;
    }
}
