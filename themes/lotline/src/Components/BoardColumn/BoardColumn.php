<?php

declare(strict_types=1);

namespace App\Theme\Components\BoardColumn;

use AIC\Component\Contracts\Component;
use App\Theme\Components\BoardStack\BoardStack;

/**
 * One column of the leads board (#30), from board-column.phtml: a section holding its stack alone, so the board's stream
 * can patch the stack in as the section's children. A card dragged from another column drops here as a move to this
 * column's stage, sent to the dragged card's action with its own form for that stage.
 */
final readonly class BoardColumn implements Component
{
    /**
     * @param string     $anchor     The column's anchor (`leads-won`), also its id.
     * @param string     $stage      Its stage's stored value (`won`), which names the dragged card's form for it
     *                               (`lead-to-won-<id>`).
     * @param BoardStack $boardStack Its heading, count and cards.
     */
    public function __construct(
        public string $anchor,
        public string $stage,
        public BoardStack $boardStack,
    ) {
    }
}
