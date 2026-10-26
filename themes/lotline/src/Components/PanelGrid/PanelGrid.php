<?php

declare(strict_types=1);

namespace App\Theme\Components\PanelGrid;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A dashboard's twelve-column grid, from panel-grid.phtml: figures and panels in order.
 */
final readonly class PanelGrid implements Component
{
    /**
     * @param list<KeyedComponent> $items The figures and panels, each keyed.
     */
    public function __construct(
        public array $items,
    ) {
    }
}
