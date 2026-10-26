<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Site\Form\ThemeQuery;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\Pagination\PageLink;
use App\Theme\Components\Pagination\Pagination;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

use function array_chunk;
use function array_key_exists;
use function count;
use function is_string;
use function number_format;
use function preg_match;
use function rawurlencode;

/**
 * The style guide's sample list of Ridgeway Motors stock, answered for real: the filter bar searches it and picks a
 * status, the Year and Price heads sort it, and the pages hold four rows each. Tessera answers each in place.
 *
 * @phpstan-import-type SampleVehicle from ThemeSampleStock
 *
 * @psalm-import-type SampleVehicle from ThemeSampleStock
 */
final readonly class ThemeSampleRecords
{
    /** The rows on a page. */
    private const int PAGE = 4;

    /** The regions a filter, a sort or a page updates. */
    private const string TARGET = 'content';

    /**
     * @param ThemeSampleStock   $themeSampleStock   The sample stock.
     * @param ThemeSampleFilters $themeSampleFilters The filter bar.
     */
    public function __construct(
        private ThemeSampleStock $themeSampleStock = new ThemeSampleStock(),
        private ThemeSampleFilters $themeSampleFilters = new ThemeSampleFilters(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: its `q`, `status`, `sort` and `page` query fields.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return list<KeyedComponent> The filter bar, the table and, when there is more than one page, the pages.
     */
    public function list(ServerRequestInterface $serverRequest): array
    {
        $themeQuery = $this->query($serverRequest);
        $matching = $this->themeSampleStock->vehicles($themeQuery);
        $pages = array_chunk($matching, self::PAGE);
        $page = $themeQuery->page <= count($pages)
            ? $themeQuery->page
            : 1;
        $vehicles = $pages[$page - 1] ?? [];
        $items = [
            new KeyedComponent('filters', $this->themeSampleFilters->filterBar($themeQuery)),
            new KeyedComponent('table', $this->table($themeQuery, $vehicles, count($matching), $page, count($pages))),
        ];

        return count($pages) > 1
            ? [...$items, new KeyedComponent('pages', $this->pagination($themeQuery, $page, count($pages)))]
            : $items;
    }

    /**
     * @param ThemeQuery          $themeQuery What the list was asked for.
     * @param list<SampleVehicle> $vehicles   The page's vehicles.
     * @param int                 $count      How many vehicles match.
     * @param int                 $page       The page shown.
     * @param int                 $pages      How many pages there are.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return DataTable The page's rows under the heads, or the empty line.
     */
    private function table(ThemeQuery $themeQuery, array $vehicles, int $count, int $page, int $pages): DataTable
    {
        $rows = [];

        foreach ($vehicles as $vehicle) {
            $rows[] = new KeyedComponent($vehicle['vin'], $this->row($vehicle));
        }

        $summary = $this->summary($count, $page, $pages);

        $columns = $this->columns($themeQuery);

        return new DataTable('inventory', $summary, 'Try fewer filters, or clear them.', self::TARGET, $columns, $rows);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return ThemeQuery Its `q`, `status`, `sort` and `page` query fields; a page that is not a number is 1.
     */
    private function query(ServerRequestInterface $serverRequest): ThemeQuery
    {
        $page = $this->field($serverRequest, 'page');

        return new ThemeQuery(
            $this->field($serverRequest, 'q'),
            $this->field($serverRequest, 'status'),
            $this->field($serverRequest, 'sort'),
            preg_match('/^[1-9][0-9]{0,3}$/', $page) === 1 ? (int) $page : 1,
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $name          A query field's name.
     *
     * @return string Its value; empty when absent or not a string.
     */
    private function field(ServerRequestInterface $serverRequest, string $name): string
    {
        $query = $serverRequest->getQueryParams();

        return array_key_exists($name, $query) && is_string($query[$name])
            ? $query[$name]
            : '';
    }

    /**
     * @param SampleVehicle $vehicle A vehicle.
     *
     * @return TableRow Its row.
     */
    private function row(array $vehicle): TableRow
    {
        $status = ThemeSampleStock::STATUSES[$vehicle['status']] ?? '';

        return new TableRow([
            new TableCell($vehicle['vin'], '/theme', true, false, null),
            new TableCell($vehicle['year'], '', false, false, null),
            new TableCell($vehicle['make'], '', false, false, null),
            new TableCell($vehicle['model'], '', false, false, null),
            new TableCell(number_format($vehicle['miles']) . ' mi', '', false, true, null),
            new TableCell('$' . number_format($vehicle['price']), '', false, true, null),
            new TableCell($status, '', false, false, $this->themeSampleStock->tone($vehicle['status'])),
        ]);
    }

    /**
     * @param ThemeQuery $themeQuery What the list was asked for.
     *
     * @return list<TableColumn> The heads; Year and Price sort, each toggling its direction.
     */
    private function columns(ThemeQuery $themeQuery): array
    {
        return [
            new TableColumn('VIN', '', 'none', '', false),
            $this->sortable('Year', 'year', $themeQuery),
            new TableColumn('Make', '', 'none', '', false),
            new TableColumn('Model', '', 'none', '', false),
            new TableColumn('Mileage', '', 'none', '', true),
            $this->sortable('Price', 'price', $themeQuery),
            new TableColumn('Status', '', 'none', '', false),
        ];
    }

    /**
     * @param string     $label      The head's text.
     * @param string     $field      What it sorts by.
     * @param ThemeQuery $themeQuery What the list was asked for.
     *
     * @return TableColumn The head, linking to the other direction when it is the sort, else ascending.
     */
    private function sortable(string $label, string $field, ThemeQuery $themeQuery): TableColumn
    {
        $ascending = $themeQuery->sort === $field;
        $descending = $themeQuery->sort === '-' . $field;

        return new TableColumn(
            $label,
            $this->href($themeQuery, $ascending ? '-' . $field : $field, 1),
            $ascending ? 'ascending' : ($descending ? 'descending' : 'none'),
            $ascending ? ' ▲' : ($descending ? ' ▼' : ''),
            $field === 'price',
        );
    }

    /**
     * @param ThemeQuery $themeQuery What the list was asked for.
     * @param int        $page       The page shown.
     * @param int        $pages      How many pages there are.
     *
     * @return Pagination A link per page.
     */
    private function pagination(ThemeQuery $themeQuery, int $page, int $pages): Pagination
    {
        $links = [];

        for ($number = 1; $number <= $pages; $number += 1) {
            $href = $this->href($themeQuery, $themeQuery->sort, $number);
            $links[] = new PageLink((string) $number, $href, $number === $page);
        }

        return new Pagination('pagination', self::TARGET, $links);
    }

    /**
     * @param ThemeQuery $themeQuery What the list was asked for.
     * @param string     $sort       The sort to link to.
     * @param int        $page       The page to link to.
     *
     * @return string The list's path with its search, status, that sort and that page.
     */
    private function href(ThemeQuery $themeQuery, string $sort, int $page): string
    {
        return '/theme?q=' . rawurlencode($themeQuery->search) . '&status=' . rawurlencode($themeQuery->status)
            . '&sort=' . rawurlencode($sort) . '&page=' . $page;
    }

    /**
     * @param int $count How many vehicles match.
     * @param int $page  The page shown.
     * @param int $pages How many pages there are.
     *
     * @return string What the table shows.
     */
    private function summary(int $count, int $page, int $pages): string
    {
        $vehicles = number_format($count) . ($count === 1 ? ' vehicle' : ' vehicles');

        return $pages > 1
            ? $vehicles . ', page ' . $page . ' of ' . $pages
            : $vehicles;
    }
}
