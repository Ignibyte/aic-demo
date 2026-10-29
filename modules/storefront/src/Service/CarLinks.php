<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use App\Inventory\Entity\ListingQuery;
use App\Storefront\Form\CarQuery;
use App\Theme\Components\Pagination\PageLink;
use App\Theme\Components\Pagination\Pagination;

use function implode;
use function range;
use function rawurlencode;

/**
 * The public list's links: a link per page, each the list's path with the query's filters and sort. A link carries
 * only the parameters that differ from their defaults, always in the same order. Tessera answers a page link with the
 * results alone: each targets `@car-results`, as the filter form does.
 */
final readonly class CarLinks
{
    /**
     * @param CarQuery $carQuery What the list was asked for.
     * @param int      $page     The page shown.
     * @param int      $pages    How many pages there are, at least 1.
     *
     * @return Pagination A link per page, keeping the filters and the sort.
     */
    public function pagination(CarQuery $carQuery, int $page, int $pages): Pagination
    {
        $links = [];

        foreach (range(1, $pages) as $number) {
            $links[] = new PageLink((string) $number, $this->href($carQuery, $number), $number === $page);
        }

        return new Pagination('car-pages', '@' . CarFilters::ANCHOR, $links);
    }

    /**
     * @param CarQuery $carQuery What the list was asked for; its filters and sort are kept.
     * @param int      $page     The page, from 1.
     *
     * @return string The list's path with those parameters.
     */
    public function href(CarQuery $carQuery, int $page): string
    {
        $parameters = $this->filters($carQuery->listingQuery);

        if ($carQuery->listingQuery->sort !== ListingQuery::NEWEST) {
            $parameters[] = 'sort=' . rawurlencode($carQuery->listingQuery->sort);
        }

        if ($page > 1) {
            $parameters[] = 'page=' . $page;
        }

        return $parameters === []
            ? CarSlugs::PAGES
            : CarSlugs::PAGES . '?' . implode('&', $parameters);
    }

    /**
     * @param ListingQuery $listingQuery What the list was asked for.
     *
     * @return list<string> Each filter it sets, as `name=value`.
     */
    private function filters(ListingQuery $listingQuery): array
    {
        $parameters = $listingQuery->make === ''
            ? []
            : ['make=' . rawurlencode($listingQuery->make)];

        if ($listingQuery->year !== null) {
            $parameters[] = 'year=' . $listingQuery->year;
        }

        if ($listingQuery->minPrice !== null) {
            $parameters[] = 'min_price=' . $listingQuery->minPrice;
        }

        if ($listingQuery->maxPrice !== null) {
            $parameters[] = 'max_price=' . $listingQuery->maxPrice;
        }

        return $parameters;
    }
}
