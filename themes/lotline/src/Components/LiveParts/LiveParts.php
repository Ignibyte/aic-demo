<?php

declare(strict_types=1);

namespace App\Theme\Components\LiveParts;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * Parts a Tessera stream keeps up to date, from live-parts.phtml: an element naming the stream around them, so Tessera
 * holds one connection while the element is on the page. A patch lands on a part, never on this element, so the
 * connection survives every patch.
 */
final readonly class LiveParts implements Component
{
    /**
     * @param string               $stream The stream's path, with its query (`/app/events?seen=…`).
     * @param list<KeyedComponent> $parts  What it holds, in order, each keyed.
     */
    public function __construct(
        public string $stream,
        public array $parts,
    ) {
    }
}
