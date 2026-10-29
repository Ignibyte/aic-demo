<?php

declare(strict_types=1);

namespace App\Theme\Components\BellCount;

use AIC\Component\Contracts\Component;
use App\Theme\Components\BellBadge\BellBadge;

/**
 * The bell's unread count, from bell-count.phtml: the holder the bell's stream fills with a fresh badge, so the
 * holder keeps its key while the badge inside it changes.
 */
final readonly class BellCount implements Component
{
    /**
     * @param BellBadge $bellBadge The badge.
     */
    public function __construct(
        public BellBadge $bellBadge,
    ) {
    }
}
