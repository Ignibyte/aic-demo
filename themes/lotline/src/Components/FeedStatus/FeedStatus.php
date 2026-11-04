<?php

declare(strict_types=1);

namespace App\Theme\Components\FeedStatus;

use AIC\Component\Contracts\Component;

/**
 * The style guide's polled feed (#34), from feed-status.phtml: one status line whose element asks Tessera to refresh
 * it from `poll` every second. It is its own poll's target, so each tick asks for this line alone.
 */
final readonly class FeedStatus implements Component
{
    /**
     * @param string $poll    The path that answers the poll, the case page's own.
     * @param string $message Whether the feed is up, going down, or back after an outage.
     */
    public function __construct(
        public string $poll,
        public string $message,
    ) {
    }
}
