<?php

declare(strict_types=1);

namespace App\Storefront\Form;

use App\Inventory\Entity\ListingQuery;

/**
 * What the public list was asked for, each value already checked: the filters and sort inventory reads the vehicles
 * by, and the page.
 */
final readonly class CarQuery
{
    /**
     * @param ListingQuery $listingQuery The make, year, price range and sort.
     * @param int          $page         The page asked for, from 1.
     */
    public function __construct(
        public ListingQuery $listingQuery,
        public int $page,
    ) {
    }
}
