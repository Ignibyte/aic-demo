<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Dashboard\Entity\LatestSale;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\Panel\Panel;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;

use function count;

/**
 * The dashboard's "Latest sales" (#10): a wide panel whose table lists the sales recorded last. The page holds it and the
 * tiles in the theme's `LiveParts`, which Tessera refreshes every five seconds (#12).
 */
final readonly class DashboardFeed
{
    /**
     * The panel's key in the dashboard's grid.
     */
    public const string KEY = 'feed';

    /**
     * How many sales the feed lists.
     */
    public const int LIMIT = 5;

    /**
     * The key of the panel's one part, the table.
     */
    private const string TABLE = 'list';

    /**
     * The heads of the text columns, in order; the price's comes after them, set right.
     */
    private const array HEADS = ['Sold on', 'Vehicle', 'Customer', 'Salesperson'];

    /**
     * Where a sale's day links: the sale's page under this path.
     */
    private const string SALES = '/app/sales/';

    /**
     * @param DashboardWords $dashboardWords Writes counts and money.
     */
    public function __construct(
        private DashboardWords $dashboardWords = new DashboardWords(),
    ) {
    }

    /**
     * @param list<LatestSale> $sales The sales recorded last, the latest first.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Panel The wide panel `feed`, its table its one part.
     */
    public function panel(array $sales): Panel
    {
        return new Panel(
            'feed',
            'Latest sales',
            'The sales recorded last. A new one appears here within seconds of being recorded.',
            true,
            [new KeyedComponent(self::TABLE, $this->table($sales))],
        );
    }

    /**
     * @param list<LatestSale> $sales The sales recorded last, the latest first.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return DataTable A row per sale: its day, linked to the sale; the vehicle; the customer; the salesperson; and the
     *                   price.
     */
    private function table(array $sales): DataTable
    {
        $rows = [];

        foreach ($sales as $sale) {
            $rows[] = new KeyedComponent('sale-' . $sale->id, $this->row($sale));
        }

        $summary = $this->dashboardWords->count(count($sales), 'sale', 'sales') . ', the latest recorded first';

        $columns = [];

        foreach (self::HEADS as $head) {
            $columns[] = new TableColumn($head, '', 'none', '', false);
        }

        $columns[] = new TableColumn('Price', '', 'none', '', true);

        return new DataTable('sales-feed', $summary, 'No sales recorded yet.', 'content', $columns, $rows);
    }

    /**
     * @param LatestSale $latestSale A sale.
     *
     * @return TableRow Its row.
     */
    private function row(LatestSale $latestSale): TableRow
    {
        return new TableRow([
            new TableCell($latestSale->soldOn, self::SALES . $latestSale->id, false, false, null),
            new TableCell($latestSale->vehicle, '', false, false, null),
            new TableCell($latestSale->customer, '', false, false, null),
            new TableCell($latestSale->salesperson, '', false, false, null),
            new TableCell($this->dashboardWords->money($latestSale->price), '', false, true, null),
        ]);
    }
}
