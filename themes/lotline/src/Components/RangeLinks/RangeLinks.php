<?php

declare(strict_types=1);

namespace App\Theme\Components\RangeLinks;

use AIC\Component\Contracts\Component;

/**
 * A row of preset ranges, from range-links.phtml: a link per range, the chosen one marked, each answered in place by
 * Tessera at its target (#21).
 */
final readonly class RangeLinks implements Component
{
    /**
     * @param string          $id     The row's id.
     * @param string          $label  What the row offers, for assistive technology.
     * @param string          $target What Tessera updates: a region (`content`) or an anchor (`@NAME`).
     * @param list<RangeLink> $links  The ranges, in order.
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $target,
        public array $links,
    ) {
    }
}
