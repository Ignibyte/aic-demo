<?php

declare(strict_types=1);

namespace App\Theme\Components\StoreHeader;

use AIC\Component\Contracts\Component;

/**
 * The public pages' header, from store-header.phtml (#17): the dealership's name, linked to the cars for sale, and a
 * line beside it.
 */
final readonly class StoreHeader implements Component
{
    /**
     * @param string $name    The dealership's name.
     * @param string $href    Where the name links: the cars for sale.
     * @param string $tagline The line beside it.
     */
    public function __construct(
        public string $name,
        public string $href,
        public string $tagline,
    ) {
    }
}
