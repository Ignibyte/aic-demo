<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Sales\Entity\Choice;
use App\Sales\Entity\Sale;
use App\Sales\Entity\SaleTotals;
use App\Sales\Form\SaleQuery;
use App\Theme\Components\FilterBar\FilterBar;
use App\Theme\Components\SelectField\SelectField;
use App\Theme\Components\StatTiles\StatTile;
use App\Theme\Components\StatTiles\StatTiles;
use InvalidArgumentException;

use function number_format;

/**
 * The sales list's filters, a month that has sales and a member of staff, as a plain GET form that Tessera answers in
 * `content`; and the totals of the sales they keep as the theme's stat tiles.
 */
final readonly class SaleFilters
{
    /**
     * @param SaleOptions $saleOptions Turns choices into the selects' options.
     * @param SaleTable   $saleTable   Writes money.
     */
    public function __construct(
        private SaleOptions $saleOptions = new SaleOptions(),
        private SaleTable $saleTable = new SaleTable(),
    ) {
    }

    /**
     * @param SaleQuery    $saleQuery What the list was asked for.
     * @param list<string> $months    The months that have sales, `YYYY-MM`, newest first.
     * @param list<Choice> $staff     The staff, by login.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FilterBar The two filters, showing what was asked.
     */
    public function filterBar(SaleQuery $saleQuery, array $months, array $staff): FilterBar
    {
        $monthOptions = $this->saleOptions->options($this->saleOptions->months($months), $saleQuery->month);
        $staffOptions = $this->saleOptions->options($staff, $saleQuery->salesperson);
        $month = new SelectField('filter-month', 'month', 'Month', 'Any month', $monthOptions, '', true);
        $salesperson = new SelectField('filter-salesperson', 'salesperson', 'Salesperson', 'Anyone', $staffOptions, '', true);

        return new FilterBar('sales-filters', Sale::PAGES, 'content', [
            new KeyedComponent('month', $month),
            new KeyedComponent('salesperson', $salesperson),
        ], Sale::PAGES);
    }

    /**
     * @param SaleTotals $saleTotals The totals of the sales the filters keep.
     *
     * @return StatTiles How many, their revenue and their gross profit.
     */
    public function totals(SaleTotals $saleTotals): StatTiles
    {
        return new StatTiles('sales-totals', 'Totals of the sales shown', [
            new StatTile('sales-count', 'Sales', number_format($saleTotals->count), 'Vehicles sold'),
            new StatTile('sales-revenue', 'Revenue', $this->saleTable->money($saleTotals->revenue), 'Their sale prices'),
            new StatTile('sales-gross', 'Gross profit', $this->saleTable->money($saleTotals->gross), 'Sale prices less what the lot paid'),
        ]);
    }
}
