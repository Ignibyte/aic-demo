<?php

declare(strict_types=1);

namespace App\Theme\Layouts;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;

/**
 * Lotline's page data: old Lotline's drawer. The header, content and footer sit in the drawer's content, the sidebar
 * in its side, which stays open from the `lg` width and opens from the top bar's toggle below it.
 */
final readonly class Page implements Component
{
    /**
     * @param Region $sidebar The navigation, in the drawer's side.
     * @param Region $header  The top bar.
     * @param Region $content The page's primary content and its stable target.
     * @param Region $footer  The page footer.
     */
    public function __construct(
        public Region $sidebar,
        public Region $header,
        public Region $content,
        public Region $footer,
    ) {
    }
}
