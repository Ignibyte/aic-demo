<?php

declare(strict_types=1);

namespace App\Theme\Components\BellBadge;

use AIC\Component\Contracts\Component;

/**
 * How many notifications are unread, from bell-badge.phtml: a daisyUI badge, hidden at none.
 */
final readonly class BellBadge implements Component
{
    /**
     * @param int    $count How many are unread.
     * @param string $label The count for a screen reader (`3 unread`).
     */
    public function __construct(
        public int $count,
        public string $label,
    ) {
    }
}
