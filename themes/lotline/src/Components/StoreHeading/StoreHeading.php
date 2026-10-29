<?php

declare(strict_types=1);

namespace App\Theme\Components\StoreHeading;

use AIC\Component\Contracts\Component;

/**
 * A public page's heading, from store-heading.phtml (#17): its title, a line under it and, when given, a link onward.
 */
final readonly class StoreHeading implements Component
{
    /**
     * @param string $id        The heading's id.
     * @param string $title     The heading.
     * @param string $lead      The line under it.
     * @param string $href      Where the link goes; empty for no link.
     * @param string $linkLabel The link's text.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $lead,
        public string $href,
        public string $linkLabel,
    ) {
    }
}
