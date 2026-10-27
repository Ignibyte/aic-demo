<?php

declare(strict_types=1);

namespace App\Inventory\Form;

/**
 * What the inventory list was asked for, each value already checked: a value that was missing, unknown or malformed
 * is its default here.
 */
final readonly class InventoryQuery
{
    /**
     * The sort when none is asked for: newest on the lot first.
     */
    public const string DEFAULT_SORT = 'added';

    /**
     * @param string   $search     The search text over make, model and VIN; empty for none.
     * @param string   $make       A make on the lot; empty for every one.
     * @param string   $status     A status's stored value; empty for every one.
     * @param int|null $minPrice   The lowest list price, in dollars; null for no bound.
     * @param int|null $maxPrice   The highest list price, in dollars; null for no bound.
     * @param string   $sort       The column it is sorted by: `year`, `make`, `model`, `mileage`, `price`, `status`
     *                             or `added`.
     * @param bool     $descending Whether that sort runs high to low.
     * @param int      $page       The page asked for, from 1.
     */
    public function __construct(
        public string $search,
        public string $make,
        public string $status,
        public ?int $minPrice,
        public ?int $maxPrice,
        public string $sort,
        public bool $descending,
        public int $page,
    ) {
    }
}
