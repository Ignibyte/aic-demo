<?php

declare(strict_types=1);

namespace App\Theme\Components\BoardStack;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * What a leads board column shows (#30), from board-stack.phtml: its heading with how many leads are at its stage, and
 * its cards newest first, or that it has none. The board's stream sends it as a column's new children.
 */
final readonly class BoardStack implements Component
{
    /**
     * @param string               $anchor The column's anchor, which its ids start with.
     * @param string               $label  The stage as staff read it.
     * @param int                  $count  How many leads are at the stage, shown or not.
     * @param list<KeyedComponent> $cards  The cards shown, newest first, each keyed by its lead.
     */
    public function __construct(
        public string $anchor,
        public string $label,
        public int $count,
        public array $cards,
    ) {
    }
}
