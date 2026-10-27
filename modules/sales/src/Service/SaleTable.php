<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Sales\Entity\Sale;
use App\Sales\Form\SaleQuery;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\Pagination\PageLink;
use App\Theme\Components\Pagination\Pagination;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;

use function abs;
use function implode;
use function number_format;
use function rawurlencode;

/**
 * The sales list's table: a row per sale on the page, its day linked to the sale, its vehicle and its customer to
 * theirs, with a link per page that keeps the filters (Tessera answers the pages in `content`); and money as staff
 * read it.
 */
final readonly class SaleTable
{
    /**
     * The regions a page link updates.
     */
    private const string TARGET = 'content';

    /**
     * @param SaleQuery  $saleQuery What the list was asked for.
     * @param list<Sale> $sales     The page's sales.
     * @param int        $count     How many sales match.
     * @param int        $page      The page shown.
     * @param int        $pages     How many pages there are.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return DataTable The page's rows under the heads, or the empty line.
     */
    public function table(SaleQuery $saleQuery, array $sales, int $count, int $page, int $pages): DataTable
    {
        $rows = [];

        foreach ($sales as $sale) {
            $rows[] = new KeyedComponent('sale-' . $sale->id, $this->row($sale));
        }

        $empty = $saleQuery->month === '' && $saleQuery->salesperson === ''
            ? 'No sales recorded yet.'
            : 'No sale matches. Try another month or salesperson, or clear the filters.';

        return new DataTable('sales-table', $this->summary($count, $page, $pages), $empty, self::TARGET, [
            new TableColumn('Sold on', '', 'none', '', false),
            new TableColumn('Vehicle', '', 'none', '', false),
            new TableColumn('Customer', '', 'none', '', false),
            new TableColumn('Salesperson', '', 'none', '', false),
            new TableColumn('Price', '', 'none', '', true),
        ], $rows);
    }

    /**
     * @param SaleQuery $saleQuery What the list was asked for.
     * @param int       $page      The page shown.
     * @param int       $pages     How many pages there are.
     *
     * @return Pagination A link per page, keeping the filters.
     */
    public function pagination(SaleQuery $saleQuery, int $page, int $pages): Pagination
    {
        $pageLinks = [];
        $number = 0;

        while ($number < $pages) {
            $number += 1;
            $pageLinks[] = new PageLink((string) $number, $this->href($saleQuery, $number), $number === $page);
        }

        return new Pagination('sales-pages', self::TARGET, $pageLinks);
    }

    /**
     * @param int $dollars Whole dollars, which a gross profit may take below 0.
     *
     * @return string As staff read them: `$19,500`, or `-$500`.
     */
    public function money(int $dollars): string
    {
        return ($dollars < 0 ? '-$' : '$') . number_format(abs($dollars));
    }

    /**
     * @param Sale $sale A sale.
     *
     * @return TableRow Its row: the day, linked to the sale; the vehicle and the customer, linked to theirs; the
     *                  salesperson; and the price.
     */
    private function row(Sale $sale): TableRow
    {
        return new TableRow([
            new TableCell($sale->soldOn, $sale->path(), false, false, null),
            new TableCell($sale->vehicle, $sale->vehiclePath(), false, false, null),
            new TableCell($sale->customer, $sale->customerPath(), false, false, null),
            new TableCell($sale->salesperson, '', false, false, null),
            new TableCell($this->money($sale->price), '', false, true, null),
        ]);
    }

    /**
     * @param SaleQuery $saleQuery What the list was asked for; its filters are kept.
     * @param int       $page      The page, from 1.
     *
     * @return string The list's path with the filters and the page, each only when it is not its default.
     */
    private function href(SaleQuery $saleQuery, int $page): string
    {
        $parameters = [];

        if ($saleQuery->month !== '') {
            $parameters[] = 'month=' . rawurlencode($saleQuery->month);
        }

        if ($saleQuery->salesperson !== '') {
            $parameters[] = 'salesperson=' . rawurlencode($saleQuery->salesperson);
        }

        if ($page > 1) {
            $parameters[] = 'page=' . $page;
        }

        return $parameters === []
            ? Sale::PAGES
            : Sale::PAGES . '?' . implode('&', $parameters);
    }

    /**
     * @param int $count How many sales match.
     * @param int $page  The page shown.
     * @param int $pages How many pages there are.
     *
     * @return string What the table shows: the count, and which page when there is more than one.
     */
    private function summary(int $count, int $page, int $pages): string
    {
        $shown = number_format($count) . ($count === 1 ? ' sale' : ' sales');

        return $pages < 2
            ? $shown
            : $shown . ', page ' . $page . ' of ' . $pages;
    }
}
