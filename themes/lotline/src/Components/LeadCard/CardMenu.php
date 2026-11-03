<?php

declare(strict_types=1);

namespace App\Theme\Components\LeadCard;

use AIC\Component\Domain\KeyedComponent;

/**
 * A lead card's "Move to" menu (#30): where a move posts, the card's column, a form per stage it can move to (each a
 * `CardMove` holding the guard fields, AIC #246, keyed by its stage), and whether the card just moved, so its menu takes
 * focus.
 */
final readonly class CardMenu
{
    /**
     * @param string               $action Where a move goes, `/app/leads/<id>/stage`, which a drag sends too.
     * @param string               $from   The anchor of the card's column.
     * @param bool                 $focus  Whether the card just moved here, so its menu's summary takes focus.
     * @param list<KeyedComponent> $moves  A `CardMove` form per other stage, in board order, keyed by the stage.
     */
    public function __construct(
        public string $action,
        public string $from,
        public bool $focus,
        public array $moves,
    ) {
    }
}
