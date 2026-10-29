<?php

declare(strict_types=1);

namespace App\Theme\Components\CarGrid;

use AIC\Component\Contracts\Component;

/**
 * The cars for sale, from car-grid.phtml (#17): a line saying how many and which page, then a card per car, or a line
 * when none matches.
 */
final readonly class CarGrid implements Component
{
    /**
     * @param string        $id      The list's id, and the stem of its summary's and its empty line's.
     * @param string        $summary How many cars match, and the page.
     * @param string        $empty   What it says when no car matches.
     * @param list<CarTile> $tiles   The page's cars.
     */
    public function __construct(
        public string $id,
        public string $summary,
        public string $empty,
        public array $tiles,
    ) {
    }
}
