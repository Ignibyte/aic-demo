<?php

declare(strict_types=1);

namespace App\Theme\Components\LazyPanel;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A part of a record's page that loads after the page (#31), from lazy-panel.phtml: one section, either a skeleton,
 * busy, whose element asks Tessera to load it from `lazy` once it is rendered, or the part arrived, whose element asks
 * Tessera to refresh it from `poll` every 30 seconds. The two carry different ids, so a morph never takes one for the
 * other and Tessera, which loads a lazy element once, always meets a skeleton as a new element.
 */
final readonly class LazyPanel implements Component
{
    /**
     * @param string               $id      Its id, and the stem of its heading's: `<key>-<VIN>-loading` for a skeleton,
     *                                      `<key>-<VIN>` arrived.
     * @param string               $heading Its heading.
     * @param string               $lazy    The route Tessera loads it from; empty once it arrived.
     * @param string               $poll    The route Tessera polls it from every 30 seconds; empty for a skeleton.
     * @param list<KeyedComponent> $body    What it shows, each keyed; empty for a skeleton.
     */
    public function __construct(
        public string $id,
        public string $heading,
        public string $lazy,
        public string $poll,
        public array $body,
    ) {
    }
}
