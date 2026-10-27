<?php

declare(strict_types=1);

namespace App\Theme\Components\TableResults;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A list's results, from table-results.phtml: its table, its pages and the values its links keep, rendered in order with
 * no element of their own, so that Tessera can swap them as one keyed component without changing the layout.
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
