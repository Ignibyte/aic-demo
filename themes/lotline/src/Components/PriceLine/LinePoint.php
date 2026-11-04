<?php

declare(strict_types=1);

namespace App\Theme\Components\PriceLine;

/**
 * One point of a price line (#31): where it sits on the drawing, and the day and price it stands for. Not a
 * Component: `PriceLine` holds a list of them.
 */
final readonly class LinePoint
{
    /**
     * @param int    $left  Its distance from the drawing's left edge.
     * @param int    $top   Its distance from the drawing's top edge.
     * @param string $day   The day, `YYYY-MM-DD`.
     * @param string $price The price that day, as staff read it (`$18,250`).
     */
    public function __construct(
        public int $left,
        public int $top,
        public string $day,
        public string $price,
    ) {
    }
}
