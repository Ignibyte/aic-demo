<?php

declare(strict_types=1);

namespace App\Theme\Components\LeadCard;

/**
 * A lead card's "Move to" menu (#30): where its forms post, the visitor's form token they carry, the card's column, the
 * stages it can move to, and whether the card just moved, so its menu takes focus.
 */
final readonly class CardMenu
{
    /**
     * @param string           $action  Where a move goes, `/app/leads/<id>/stage`.
     * @param string           $token   The visitor's form token.
     * @param string           $from    The anchor of the card's column.
     * @param bool             $focus   Whether the card just moved here, so its menu's summary takes focus.
     * @param list<MoveChoice> $choices Every other stage, in board order.
     */
    public function __construct(
        public string $action,
        public string $token,
        public string $from,
        public bool $focus,
        public array $choices,
    ) {
    }
}
