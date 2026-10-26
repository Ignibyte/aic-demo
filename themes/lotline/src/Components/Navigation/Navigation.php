<?php

declare(strict_types=1);

namespace App\Theme\Components\Navigation;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * The sidebar, from navigation.phtml: the site's name linking home, then a daisyUI menu of its links.
 */
final readonly class Navigation implements Component
{
    /**
     * @param string               $name  The site's name.
     * @param list<KeyedComponent> $links Each link, a NavLink keyed by its name, in order.
     */
    public function __construct(
        public string $name,
        public array $links,
    ) {
    }
}
