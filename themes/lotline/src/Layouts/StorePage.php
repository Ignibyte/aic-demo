<?php

declare(strict_types=1);

namespace App\Theme\Layouts;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;

/**
 * The public pages' data (#17): the dealership's header, the page's content and its footer, one column with no drawer.
 * Its regions are named apart from the staff page's `sidebar`, `header` and `footer`, so no staff block, which places
 * itself in one of those, renders on a public page.
 */
final readonly class StorePage implements Component
{
    /**
     * @param Region $masthead The dealership's header.
     * @param Region $content  The page's primary content and its stable target.
     * @param Region $colophon The dealership's footer.
     */
    public function __construct(
        public Region $masthead,
        public Region $content,
        public Region $colophon,
    ) {
    }
}
