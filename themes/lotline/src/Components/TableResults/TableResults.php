<?php

declare(strict_types=1);

namespace App\Theme\Components\TableResults;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A list's results, from table-results.phtml: its table, its pages and the values its links keep, in order, in one
 * element spaced as the card spaces its parts, so that Tessera can swap them as one keyed component marked on it.
 */
final readonly class TableResults implements Component
{
    /**
     * @param list<KeyedComponent> $parts What it holds, in order, each keyed.
     */
    public function __construct(
        public array $parts,
    ) {
    }
}
