<?php

declare(strict_types=1);

namespace App\Theme\Components\BellItems;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * The bell's menu of notifications, from bell-items.phtml: newest first, five shown. The bell's stream puts a new one
 * at the top, so the list hides every item after the fifth rather than the stream removing the oldest.
 */
final readonly class BellItems implements Component
{
    /**
     * @param list<KeyedComponent> $items The latest notifications, newest first, each a BellItem keyed by its id.
     * @param string               $empty What to say while there are none; hidden once an item is above it.
     */
    public function __construct(
        public array $items,
        public string $empty,
    ) {
    }
}
