<?php

declare(strict_types=1);

namespace App\Leads\Entity;

/**
 * One change of a lead's stage (#30), a `lead_moves` row as the board's stream reads it: its id, which the stream sends
 * as its events' id, and the two columns it touched.
 */
final readonly class StageMove
{
    /**
     * @param int       $id      Its row.
     * @param LeadStage $from    The stage the lead left.
     * @param LeadStage $toStage The stage it went to.
     */
    public function __construct(
        public int $id,
        public LeadStage $from,
        public LeadStage $toStage,
    ) {
    }
}
