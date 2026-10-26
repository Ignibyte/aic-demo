<?php

declare(strict_types=1);

namespace App\Theme\Components\StatusBadge;

use AIC\Component\Contracts\Component;

/**
 * A record's status, from status-badge.phtml: a small daisyUI badge in its tone.
 */
final readonly class StatusBadge implements Component
{
    /**
     * @param string    $label     The status, as staff read it.
     * @param BadgeTone $badgeTone Its colour.
     * @param string    $id        The badge's id; empty for none.
     */
    public function __construct(
        public string $label,
        public BadgeTone $badgeTone,
        public string $id = '',
    ) {
    }
}
