<?php

declare(strict_types=1);

namespace App\Theme\Components\CompareDrawer;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * The comparison's side drawer (#33), from compare-drawer.phtml: a panel fixed at the right of the page, shown while
 * `$compareOpen` holds, with its heading, a Close button, and the chosen cars side by side, or a line saying why there
 * are none. Every store page renders it empty; `/compare` fills it for a Tessera request, which the Compare link sends.
 */
final readonly class CompareDrawer implements Component
{
    /**
     * @param string               $message What it says in place of a table: why the cars cannot be compared, or that
     *                                      none is left; empty for none.
     * @param list<KeyedComponent> $tables  The cars side by side, at most one `CompareTable`, keyed `table`; none for
     *                                      an empty drawer or a message.
     */
    public function __construct(
        public string $message,
        public array $tables,
    ) {
    }
}
