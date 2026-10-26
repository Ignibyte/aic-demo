<?php

declare(strict_types=1);

namespace App\Theme\Components\IconLegend;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * The theme's icons with their names, from icon-legend.phtml.
 */
final readonly class IconLegend implements Component
{
    /**
     * @param list<KeyedComponent> $entries Each IconEntry, keyed by its name.
     */
    public function __construct(
        public array $entries,
    ) {
    }
}
