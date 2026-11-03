<?php

declare(strict_types=1);

namespace App\Theme\Components\CardMove;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\GuardFields;
use App\Theme\Components\LeadCard\MoveChoice;

/**
 * One form of a lead card's "Move to" menu (#30), from card-move.phtml: it posts its stage to the lead's stage route,
 * and with script its button sends that as a Tessera `PATCH` naming the card's column and the stage's. It holds AIC's
 * guard fields (AIC #246), the form token and the honeypot, so a plain post passes the form guard; the menu keys one
 * per stage, since a component in a template's loop is keyed.
 */
final readonly class CardMove implements Component
{
    /**
     * @param int         $lead        The lead's id, which the form's and the button's ids end with.
     * @param string      $action      Where the move goes, `/app/leads/<id>/stage`.
     * @param string      $from        The anchor of the card's column.
     * @param MoveChoice  $moveChoice  The stage it moves to.
     * @param GuardFields $guardFields The viewer's form token and the honeypot, which the form renders first.
     */
    public function __construct(
        public int $lead,
        public string $action,
        public string $from,
        public MoveChoice $moveChoice,
        public GuardFields $guardFields,
    ) {
    }
}
