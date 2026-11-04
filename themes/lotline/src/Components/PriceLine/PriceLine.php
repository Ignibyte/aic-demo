<?php

declare(strict_types=1);

namespace App\Theme\Components\PriceLine;

use AIC\Component\Contracts\Component;

/**
 * A list price over time as a server-drawn SVG line (#31), from price-line.phtml: a polyline through its points on a
 * drawing `width` by `height`, a circle on each, and under it the points as a list of days and prices, with a line
 * saying so when no change was recorded. Its caller places the points; the template only writes them.
 */
final readonly class PriceLine implements Component
{
    /**
     * @param string          $id     Its id, and the stem of its title's.
     * @param string          $title  What it shows, the drawing's accessible name.
     * @param int             $width  The drawing's width.
     * @param int             $height The drawing's height.
     * @param string          $line   The polyline's points, `x,y` pairs separated by spaces.
     * @param list<LinePoint> $points The points, oldest first.
     * @param string          $empty  What it says when no change was recorded; empty when there are changes.
     */
    public function __construct(
        public string $id,
        public string $title,
        public int $width,
        public int $height,
        public string $line,
        public array $points,
        public string $empty,
    ) {
    }
}
