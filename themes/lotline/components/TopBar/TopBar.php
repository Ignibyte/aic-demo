<?php

declare(strict_types=1);

namespace App\Theme\Components\TopBar;

use AIC\Component\Contracts\Component;

/**
 * The page's top bar, from top-bar.phtml: the drawer's toggle below the `lg` width, then the page's title.
 */
final readonly class TopBar implements Component
{
    /**
     * @param string $title The page's title.
     */
    public function __construct(
        public string $title,
    ) {
    }
}
