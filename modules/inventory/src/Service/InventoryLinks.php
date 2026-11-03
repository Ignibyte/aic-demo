<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use App\Inventory\Form\InventoryQuery;
use App\Theme\Components\Pagination\PageLink;
use App\Theme\Components\Pagination\Pagination;

use function implode;
use function rawurlencode;

/**
 * The inventory list's links: its path with the query's search and filters, a sort and a page. A link carries only
 * the parameters that differ from their defaults, always in the same order; and the list's page links, built from
 * them, which `InventoryTable` places (moved here in #29, so the table's class keeps within PHPMD's coupling limit).
 */
final readonly class InventoryLinks
{
    /**
     * The list's path.
     */
    public const string PATH = '/app/inventory';

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for; its search and filters are kept.
     * @param string         $sort           The column to sort by.
     * @param bool           $descending     Whether that sort runs high to low.
     * @param int            $page           The page, from 1.
     *
     * @return string The list's path with those parameters.
     */
    public function href(InventoryQuery $inventoryQuery, string $sort, bool $descending, int $page): string
    {
        $parameters = $this->filters($inventoryQuery);
        $sortValue = $this->sortValue($sort, $descending);

        if ($sortValue !== '') {
            $parameters[] = 'sort=' . rawurlencode($sortValue);
        }

        if ($page > 1) {
            $parameters[] = 'page=' . $page;
        }

        return $parameters === []
            ? self::PATH
            : self::PATH . '?' . implode('&', $parameters);
    }

    /**
     * @param string $sort       The column it is sorted by.
     * @param bool   $descending Whether that sort runs high to low.
     *
     * @return string The `sort` parameter: the column, after `-` when descending; empty for the default sort.
     */
    public function sortValue(string $sort, bool $descending): string
    {
        if ($sort === InventoryQuery::DEFAULT_SORT && $descending) {
            return '';
        }

        return $descending
            ? '-' . $sort
            : $sort;
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     * @param int            $page           The page shown.
     * @param int            $pages          How many pages there are.
     * @param string         $target         What a page link updates: the results' anchor (`@inventory-results`).
     *
     * @return Pagination A link per page, keeping the search, the filters and the sort.
     */
    public function pages(InventoryQuery $inventoryQuery, int $page, int $pages, string $target): Pagination
    {
        $links = [];

        for ($number = 1; $number <= $pages; $number += 1) {
            $href = $this->href($inventoryQuery, $inventoryQuery->sort, $inventoryQuery->descending, $number);
            $links[] = new PageLink((string) $number, $href, $number === $page);
        }

        return new Pagination('vehicles-pages', $target, $links);
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     *
     * @return list<string> Its search and each filter it sets, as `name=value`.
     */
    private function filters(InventoryQuery $inventoryQuery): array
    {
        $parameters = [];

        if ($inventoryQuery->search !== '') {
            $parameters[] = 'q=' . rawurlencode($inventoryQuery->search);
        }

        if ($inventoryQuery->make !== '') {
            $parameters[] = 'make=' . rawurlencode($inventoryQuery->make);
        }

        if ($inventoryQuery->status !== '') {
            $parameters[] = 'status=' . rawurlencode($inventoryQuery->status);
        }

        if ($inventoryQuery->minPrice !== null) {
            $parameters[] = 'min_price=' . $inventoryQuery->minPrice;
        }

        if ($inventoryQuery->maxPrice !== null) {
            $parameters[] = 'max_price=' . $inventoryQuery->maxPrice;
        }

        return $parameters;
    }
}
