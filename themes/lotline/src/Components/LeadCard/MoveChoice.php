<?php

declare(strict_types=1);

namespace App\Theme\Components\LeadCard;

/**
 * One stage a lead card can move to (#30): its stored value, which the form posts and its ids end with, its label, and
 * its column's anchor, which a move names as its second target.
 */
final readonly class MoveChoice
{
    /**
     * @param string $stage  The stage's stored value (`won`).
     * @param string $label  The stage as staff read it.
     * @param string $anchor Its column's anchor (`leads-won`).
     */
    public function __construct(
        public string $stage,
        public string $label,
        public string $anchor,
    ) {
    }
}
