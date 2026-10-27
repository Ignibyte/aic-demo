<?php

declare(strict_types=1);

namespace App\Theme\Components\TopBar;

use AIC\Component\Contracts\Component;

/**
 * The page's top bar, from top-bar.phtml: the drawer's toggle below the `lg` width, then the page's title and, when
 * it has one, a line under it.
 */
final readonly class TopBar implements Component
{
    /**
     * @param string $title The page's title.
     * @param string $note  A line under the title; empty for none.
     */
    public function __construct(
        public string $title,
        public string $note = '',
    ) {
    }
}
