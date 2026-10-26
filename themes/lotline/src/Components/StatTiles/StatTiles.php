<?php

declare(strict_types=1);

namespace App\Theme\Components\StatTiles;

use AIC\Component\Contracts\Component;

/**
 * A row of figures, from stat-tiles.phtml: daisyUI stats, stacked until `xl`.
 */
final readonly class StatTiles implements Component
{
    /**
     * @param string         $id    The row's id.
     * @param string         $label What the figures are, for assistive technology.
     * @param list<StatTile> $tiles The figures, in order.
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $tiles,
    ) {
    }
}
