<?php

declare(strict_types=1);

namespace App\Theme\Components\Bell;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use App\Theme\Components\Icon\Icon;

/**
 * The top bar's notification bell, from bell.phtml: a daisyUI dropdown whose button is the bell and the unread count,
 * and whose menu is the latest notifications and a link to them all. Its root carries the stream that keeps it live;
 * the count and the list are keyed, so the stream patches each on its own.
 */
final readonly class Bell implements Component
{
    /**
     * @param string         $stream The stream Tessera holds while the bell is on the page; empty for none.
     * @param Icon           $icon   The bell icon.
     * @param KeyedComponent $count  The unread count, keyed `count`.
     * @param KeyedComponent $items  The latest notifications, keyed `items`.
     * @param string         $all    The path of every notification.
     */
    public function __construct(
        public string $stream,
        public Icon $icon,
        public KeyedComponent $count,
        public KeyedComponent $items,
        public string $all,
    ) {
    }
}
