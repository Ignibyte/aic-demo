<?php

declare(strict_types=1);

namespace App\Theme\Components\CaseWatch;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * One of the style guide's resilience cases (#34), from case-watch.phtml: a section that listens for the Tessera event
 * its case raises and counts it in a signal of its own, shown above what it holds. `tessera:expired` and
 * `tessera:indeterminate` are counted and left to Tessera, so its prompt and its alert show as they would anywhere;
 * `tessera:error` is counted and cancelled, so the console stays clean. Tessera reports an indeterminate write as an
 * error too, which that watch cancels without counting.
 */
final readonly class CaseWatch implements Component
{
    /**
     * @param string               $id           The section's id; its count is `{id}-count`.
     * @param WatchedEvent         $watchedEvent The event it counts.
     * @param list<KeyedComponent> $body         What it holds, in order, each keyed.
     */
    public function __construct(
        public string $id,
        public WatchedEvent $watchedEvent,
        public array $body,
    ) {
    }
}
