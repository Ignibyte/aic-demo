<?php

declare(strict_types=1);

namespace App\Theme\Components\LiveParts;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * Parts Tessera keeps up to date by polling, from live-parts.phtml (#12): a grid item spanning a dashboard's row, laying
 * its parts out on the grid's twelve columns, whose element asks Tessera to refresh it from `poll` every five seconds.
 * Each refresh is one request for this component, answered with its parts; Tessera skips it while the tab is hidden and
 * sends one in twenty while the window has no focus.
 */
final readonly class LiveParts implements Component
{
    /**
     * @param string               $poll  The path that answers the refresh, the page's own (`/app`).
     * @param list<KeyedComponent> $parts What it holds, in order, each keyed.
     */
    public function __construct(
        public string $poll,
        public array $parts,
    ) {
    }
}
