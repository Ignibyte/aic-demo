<?php

declare(strict_types=1);

namespace App\Theme\Components\LeadBoard;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * The leads board (#30), from lead-board.phtml: its five columns side by side, the stream that keeps them current while
 * the board is open, and the region signals a drag fills (the card being dragged and its column's anchor).
 */
final readonly class LeadBoard implements Component
{
    /**
     * @param string               $stream  The board's stream, which `data-stream` opens.
     * @param list<KeyedComponent> $columns The columns in board order, each keyed by its stage and anchored.
     */
    public function __construct(
        public string $stream,
        public array $columns,
    ) {
    }
}
