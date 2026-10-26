<?php

declare(strict_types=1);

namespace App\Theme\Components\DetailCard;

use AIC\Component\Contracts\Component;
use App\Theme\Components\StatusBadge\StatusBadge;

/**
 * One record, from detail-card.phtml: its title and status, its facts as a definition list, a note, and its actions as
 * buttons.
 */
final readonly class DetailCard implements Component
{
    /**
     * @param string           $id          The title's id.
     * @param string           $title       The record's title.
     * @param StatusBadge      $statusBadge Its status.
     * @param list<Fact>       $facts       Its facts, in order.
     * @param string           $note        A line under the facts; empty for none.
     * @param list<ButtonLink> $actions     What can be done with it.
     */
    public function __construct(
        public string $id,
        public string $title,
        public StatusBadge $statusBadge,
        public array $facts,
        public string $note,
        public array $actions,
    ) {
    }
}
