<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Inventory\Form\InventoryQuery;
use App\Inventory\Form\VehicleRowForm;
use App\Theme\Components\BulkBar\BulkBar;
use App\Theme\Components\BulkBar\BulkConfirm;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\FormValue\FormValue;
use App\Theme\Components\SelectField\SelectOption;
use App\Theme\Components\TableResults\TableResults;
use App\Theme\Components\TableTotals\TableTotals;
use InvalidArgumentException;

use function implode;
use function number_format;
use function preg_match;
use function strtolower;

/**
 * The inventory list's results: the action bar (#29), the totals line, a row per vehicle on the page, heads that sort
 * (a second click on the sorted one reverses it), a link per page, each link keeping the search and filters, and the
 * sort the filter form sends. `InventoryResults` builds them from what it read when the results are rendered (#14), its
 * rows from `VehicleRowForm`. Tessera answers a filter, a sort or a page with them alone: each control targets them as
 * `@inventory-results`. A row's own answer (#28) and a bulk edit's answer hold the results of their rows alone, with
 * the bar and the totals line, so each row sits where the list puts it.
 */
final readonly class InventoryTable
{
    /**
     * The results' anchor (AIC #188): the list's panel carries it on the results, its controls target `@inventory-results`,
     * and Tessera finds the results' path on the page, so no code here spells it.
     */
    public const string ANCHOR = 'inventory-results';

    /**
     * The totals line's anchor (#29), which a bulk edit's action names beside its rows'.
     */
    public const string TOTALS = 'inventory-totals';

    /**
     * The action bar's anchor (#29).
     */
    public const string BULK = 'inventory-bulk';

    /**
     * The table's id, which the totals line's lines are named after (`vehicles-table-summary`).
     */
    private const string TABLE = 'vehicles-table';

    /**
     * @param InventoryLinks  $inventoryLinks  Writes the links.
     * @param VehicleStatuses $vehicleStatuses The statuses and the lot locations, which the bar offers.
     */
    public function __construct(
        private InventoryLinks $inventoryLinks = new InventoryLinks(),
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
    ) {
    }

    /**
     * @param InventoryQuery       $inventoryQuery What the list was asked for.
     * @param list<KeyedComponent> $rows           The page's vehicles' rows.
     * @param int                  $page           The page shown.
     * @param int                  $pages          How many pages there are.
     * @param BulkBar              $bulkBar        The action bar.
     * @param TableTotals          $tableTotals    The totals line.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return TableResults The sort the filter form sends, the bar, the totals line, the table and, past one page, the
     *                      pages.
     */
    public function results(
        InventoryQuery $inventoryQuery,
        array $rows,
        int $page,
        int $pages,
        BulkBar $bulkBar,
        TableTotals $tableTotals,
    ): TableResults {
        $sort = $this->inventoryLinks->sortValue($inventoryQuery->sort, $inventoryQuery->descending);
        $parts = [
            new KeyedComponent('sort', new FormValue(InventoryFilters::FORM, 'sort', $sort)),
            new KeyedComponent('bulk', $bulkBar, '', self::BULK),
            new KeyedComponent('totals', $tableTotals, '', self::TOTALS),
            new KeyedComponent('table', $this->table($inventoryQuery, $rows)),
        ];

        if ($pages > 1) {
            $parts[] = new KeyedComponent('pages', $this->inventoryLinks->pages($inventoryQuery, $page, $pages, '@' . self::ANCHOR));
        }

        return new TableResults($parts);
    }

    /**
     * @param list<KeyedComponent> $rows        The rows a row's own answer (#28) or a bulk edit's answer (#29) holds.
     * @param BulkBar              $bulkBar     The action bar, which each row's box posts with.
     * @param TableTotals          $tableTotals The totals line.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return TableResults The results holding those rows alone, with the bar and the totals line, under the keys the
     *                      list's results have, so each sits at the path the list gives it.
     */
    public function touched(array $rows, BulkBar $bulkBar, TableTotals $tableTotals): TableResults
    {
        $inventoryQuery = new InventoryQuery('', '', '', null, null, InventoryQuery::DEFAULT_SORT, true, 1);

        return new TableResults([
            new KeyedComponent('bulk', $bulkBar, '', self::BULK),
            new KeyedComponent('totals', $tableTotals, '', self::TOTALS),
            new KeyedComponent('table', $this->table($inventoryQuery, $rows)),
        ]);
    }

    /**
     * @param string $token      The visitor's form token.
     * @param string $back       The list the bar is shown on, path and query.
     * @param bool   $confirming Whether it asks to confirm a delete posted without script, the delete chosen.
     * @param int    $ticked     How many rows are ticked as it arrives.
     *
     * @return BulkBar The action bar: set a status (sold is a sale's), move to a lot location, or delete.
     */
    public function bar(string $token, string $back, bool $confirming = false, int $ticked = 0): BulkBar
    {
        $warning = 'A deleted vehicle is gone from the lot for good; this cannot be undone.';
        $bulkConfirm = new BulkConfirm('delete', 'Delete the ticked vehicles?', $warning, $confirming, $ticked);

        return new BulkBar(
            VehicleRowForm::BULK,
            VehicleRowForm::ACTION,
            $token,
            $back,
            'With the ticked vehicles',
            $this->choices($confirming),
            VehicleRowForm::ANCHOR,
            self::TOTALS,
            self::ANCHOR,
            $bulkConfirm,
        );
    }

    /**
     * @param int                $count    How many vehicles match.
     * @param int                $page     The page shown.
     * @param int                $pages    How many pages there are.
     * @param array<string, int> $statuses How many match with each status, by its stored value.
     * @param string             $outcome  What a bulk edit did, or why it was refused; empty for none.
     * @param bool               $refused  Whether the outcome is a refusal.
     * @param list<string>       $vins     The VINs of the rows the page shows, in order.
     *
     * @return TableTotals The totals line: the count and the page, the counts by status, the outcome, the page's VINs.
     */
    public function totals(
        int $count,
        int $page,
        int $pages,
        array $statuses,
        string $outcome,
        bool $refused,
        array $vins,
    ): TableTotals {
        return new TableTotals(
            self::TABLE,
            $this->summary($count, $page, $pages),
            $this->counts($statuses),
            $outcome,
            $refused,
            VehicleRowForm::BULK,
            'page[]',
            $vins,
            $this->quoted($vins),
        );
    }

    /**
     * @param list<string> $vins The VINs of the rows the page shows, in order.
     *
     * @return string Each as an expression's quoted list item, joined (`'A','B'`). Only a VIN in form (17 capitals and
     *                digits) goes in, so no stored value can write code into the expression.
     */
    private function quoted(array $vins): string
    {
        $quoted = [];

        foreach ($vins as $vin) {
            if (preg_match('/^[0-9A-Z]{17}$/', $vin) !== 1) {
                continue;
            }

            $quoted[] = "'" . $vin . "'";
        }

        return implode(',', $quoted);
    }

    /**
     * @param bool $confirming Whether a delete is being confirmed, so it is the one chosen.
     *
     * @return list<SelectOption> The bar's choices: each status but sold, each lot location, and delete.
     */
    private function choices(bool $confirming): array
    {
        $choices = [
            new SelectOption('status:in_stock', 'Set to In stock', false),
            new SelectOption('status:on_hold', 'Set to On hold', false),
        ];

        foreach ($this->vehicleStatuses->locations() as $vehicleLocation) {
            $choices[] = new SelectOption('location:' . $vehicleLocation->value, 'Move to ' . $vehicleLocation->label(), false);
        }

        $choices[] = new SelectOption('delete', 'Delete', $confirming);

        return $choices;
    }

    /**
     * @param array<string, int> $statuses How many vehicles match with each status, by its stored value.
     *
     * @return string Each status that any has, with how many, in the statuses' order (`42 in stock · 10 on hold`).
     */
    private function counts(array $statuses): string
    {
        $counts = [];

        foreach ($this->vehicleStatuses->all() as $vehicleStatus) {
            $number = $statuses[$vehicleStatus->value] ?? 0;

            if ($number <= 0) {
                continue;
            }

            $counts[] = number_format($number) . ' ' . strtolower($vehicleStatus->label());
        }

        return implode(' · ', $counts);
    }

    /**
     * @param InventoryQuery       $inventoryQuery What the list was asked for.
     * @param list<KeyedComponent> $rows           The rows the table shows.
     *
     * @return DataTable The rows under the heads, or the empty line; the totals line says what it shows.
     */
    private function table(InventoryQuery $inventoryQuery, array $rows): DataTable
    {
        $empty = 'No vehicle matches. Try fewer filters, or clear them.';

        return new DataTable(self::TABLE, '', $empty, '@' . self::ANCHOR, $this->columns($inventoryQuery), $rows);
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     *
     * @return list<TableColumn> The heads, the page's box in the VIN's; all but VIN, Body, Colour and Location sort.
     */
    private function columns(InventoryQuery $inventoryQuery): array
    {
        return [
            new TableColumn('VIN', '', 'none', '', false, 'Select every vehicle on this page'),
            $this->sortable('Year', 'year', $inventoryQuery, false),
            $this->sortable('Make', 'make', $inventoryQuery, false),
            $this->sortable('Model', 'model', $inventoryQuery, false),
            new TableColumn('Body', '', 'none', '', false),
            new TableColumn('Colour', '', 'none', '', false),
            $this->sortable('Mileage', 'mileage', $inventoryQuery, true),
            $this->sortable('Price', 'price', $inventoryQuery, true),
            $this->sortable('Status', 'status', $inventoryQuery, false),
            new TableColumn('Location', '', 'none', '', false),
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
