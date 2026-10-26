<?php

declare(strict_types=1);

namespace App\Theme\Components\SiteFooter;

use AIC\Component\Contracts\Component;

/**
 * The footer line on every page, from site-footer.phtml.
 */
final readonly class SiteFooter implements Component
{
    /**
     * @param string $name       The site's name.
     * @param string $dealership The dealership it runs.
     */
    public function __construct(
        public string $name,
        public string $dealership,
    ) {
    }
}
