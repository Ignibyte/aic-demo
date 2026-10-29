<?php

declare(strict_types=1);

namespace App\Theme\Components\BellItem;

use AIC\Component\Contracts\Component;

/**
 * One notification in the bell's menu, from bell-item.phtml: its subject, set bold while unread, and when it came,
 * linking to its opening.
 */
final readonly class BellItem implements Component
{
    /**
     * @param string $id      Its element id (`bell-item-12`).
     * @param string $subject Its subject.
     * @param string $sent    When it came (`2026-10-29 05:12 UTC`).
     * @param string $href    Its opening, which marks it read and goes on to what it is about.
     * @param bool   $read    Whether it has been read.
     */
    public function __construct(
        public string $id,
        public string $subject,
        public string $sent,
        public string $href,
        public bool $read,
    ) {
    }
}
