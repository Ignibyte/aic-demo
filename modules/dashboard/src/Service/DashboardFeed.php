<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Dashboard\Entity\LatestSale;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\LiveParts\LiveParts;
use App\Theme\Components\Panel\Panel;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;

use function count;

/**
 * The dashboard's "Latest sales" (#10): a wide panel whose table lists the sales recorded last, inside the theme's
 * `LiveParts`, which holds the dashboard's stream, so the stream can patch the table while the panel stays.
 */
final readonly class DashboardFeed
{
    /**
     * The panel's key in the dashboard's grid.
     */
    public const string KEY = 'feed';

    /**
     * The table's Tessera path, which the stream patches: the grid's item `KEY`, its panel's part `LIVE` and the
     * `LiveParts`' part `TABLE`, as the render pass keys them. It changes if the page moves the panel.
     */
    public const string TARGET = 'content/c0/PanelGrid.1:' . self::KEY . '/Panel.1:' . self::LIVE
        . '/LiveParts.1:' . self::TABLE;

    /**
     * How many sales the feed lists.
     */
    public const int LIMIT = 5;

    /**
     * The key of the panel's one part, the `LiveParts` holding the stream.
     */
    private const string LIVE = 'live';

    /**
     * The key of the table inside the `LiveParts`.
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
     * @param string           $stream The stream's path, with what the page saw.
     * @param list<LatestSale> $sales  The sales recorded last, the latest first.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Panel The wide panel `feed`, its table inside the stream's element.
     */
    public function panel(string $stream, array $sales): Panel
    {
        $liveParts = new LiveParts($stream, [new KeyedComponent(self::TABLE, $this->table($sales))]);

        return new Panel(
            'feed',
            'Latest sales',
            'The sales recorded last. A new one appears here as it is recorded.',
            true,
            [new KeyedComponent(self::LIVE, $liveParts)],
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
    public function table(array $sales): DataTable
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
