<?php

declare(strict_types=1);

namespace App\Theme\Components\LeadCard;

use AIC\Component\Contracts\Component;

/**
 * A lead on the leads board (#30), from lead-card.phtml: who sent it, about which car, what kind, whose it is and the day
 * it came in. For a viewer who may move it, it is draggable and holds its "Move to" menu; for anyone else it shows no
 * menu and does not drag.
 */
final readonly class LeadCard implements Component
{
    /**
     * @param int      $id       The lead's id, which the card's ids and its moves name.
     * @param string   $path     The lead's page.
     * @param string   $name     The visitor's name.
     * @param string   $car      The car's title.
     * @param string   $kind     The kind, as staff read it.
     * @param string   $seller   The salesperson's name, or Unassigned.
     * @param string   $received The day it came in, `YYYY-MM-DD`.
     * @param bool     $movable  Whether the viewer may move it, so it is draggable and shows its menu.
     * @param CardMenu $cardMenu Its menu, shown only when it is movable.
     */
    public function __construct(
        public int $id,
        public string $path,
        public string $name,
        public string $car,
        public string $kind,
        public string $seller,
        public string $received,
        public bool $movable,
        public CardMenu $cardMenu,
    ) {
    }
}
