<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

/**
 * What the public list of vehicles for sale asks for (#17), each value checked already: a value that was missing,
 * unknown or malformed is its default here.
 */
final readonly class ListingQuery
{
    /**
     * Newest on the lot first, the default.
     */
    public const string NEWEST = 'newest';

    /**
     * The lowest price first.
     */
    public const string PRICE = 'price';

    /**
     * The highest price first.
     */
    public const string PRICE_DESCENDING = '-price';

    /**
     * @param string   $make     A make for sale; empty for every one.
     * @param int|null $year     A model year for sale; null for every one.
     * @param int|null $minPrice The lowest list price, in dollars; null for no bound.
     * @param int|null $maxPrice The highest list price, in dollars; null for no bound.
     * @param string   $sort     `newest`, `price` or `-price`.
     */
    public function __construct(
        public string $make,
        public ?int $year,
        public ?int $minPrice,
        public ?int $maxPrice,
        public string $sort,
    ) {
    }
}
