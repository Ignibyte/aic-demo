<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Inventory\Form\InventoryQuery;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\FormValue\FormValue;
use App\Theme\Components\Pagination\PageLink;
use App\Theme\Components\Pagination\Pagination;
use App\Theme\Components\TableResults\TableResults;
use InvalidArgumentException;

use function number_format;

/**
 * The inventory list's results: a row per vehicle on the page, heads that sort (a second click on the sorted one
 * reverses it), a link per page, each link keeping the search and filters, and the sort the filter form sends.
 * `InventoryResults` builds them from what it read when the results are rendered (#14), its rows from `VehicleRowForm`.
 * Tessera answers a filter, a sort or a page with them alone: each control targets them as `@inventory-results`. A
 * row's own answer (#28) holds the results of that row alone, so the row sits where the list puts it.
 */
final readonly class InventoryTable
{
    /**
     * The results' anchor (AIC #188): the list's panel carries it on the results, its controls target `@inventory-results`,
     * and Tessera finds the results' path on the page, so no code here spells it.
     */
    public const string ANCHOR = 'inventory-results';

    /**
     * @param InventoryLinks $inventoryLinks Writes the links.
     */
    public function __construct(
        private InventoryLinks $inventoryLinks = new InventoryLinks(),
    ) {
    }

    /**
     * @param InventoryQuery       $inventoryQuery What the list was asked for.
     * @param list<KeyedComponent> $rows           The page's vehicles' rows.
     * @param int                  $count          How many vehicles match.
     * @param int                  $page           The page shown.
     * @param int                  $pages          How many pages there are.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return TableResults The sort the filter form sends, the table and, past one page, the pages.
     */
    public function results(
        InventoryQuery $inventoryQuery,
        array $rows,
        int $count,
        int $page,
        int $pages,
    ): TableResults {
        $sort = $this->inventoryLinks->sortValue($inventoryQuery->sort, $inventoryQuery->descending);
        $parts = [
            new KeyedComponent('sort', new FormValue(InventoryFilters::FORM, 'sort', $sort)),
            new KeyedComponent('table', $this->table($inventoryQuery, $rows, $count, $page, $pages)),
        ];

        if ($pages > 1) {
            $parts[] = new KeyedComponent('pages', $this->pagination($inventoryQuery, $page, $pages));
        }

        return new TableResults($parts);
    }

    /**
     * @param KeyedComponent $keyedComponent One vehicle's row, shown or as a form.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return TableResults The results holding that row alone, under the keys the list's results have, for a row's own
     *                      answer (#28).
     */
    public function alone(KeyedComponent $keyedComponent): TableResults
    {
        $inventoryQuery = new InventoryQuery('', '', '', null, null, InventoryQuery::DEFAULT_SORT, true, 1);

        return new TableResults([new KeyedComponent('table', $this->table($inventoryQuery, [$keyedComponent], 1, 1, 1))]);
    }

    /**
     * @param InventoryQuery       $inventoryQuery What the list was asked for.
     * @param list<KeyedComponent> $rows           The page's vehicles' rows.
     * @param int                  $count          How many vehicles match.
     * @param int                  $page           The page shown.
     * @param int                  $pages          How many pages there are.
     *
     * @return DataTable The page's rows under the heads, or the empty line.
     */
    private function table(
        InventoryQuery $inventoryQuery,
        array $rows,
        int $count,
        int $page,
        int $pages,
    ): DataTable {
        $summary = $this->summary($count, $page, $pages);
        $empty = 'No vehicle matches. Try fewer filters, or clear them.';

        return new DataTable('vehicles-table', $summary, $empty, '@' . self::ANCHOR, $this->columns($inventoryQuery), $rows);
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

        return new Pagination('vehicles-pages', '@' . self::ANCHOR, $links);
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
