<?php

declare(strict_types=1);

namespace App\Theme\Components\StoreFooter;

use AIC\Component\Contracts\Component;

/**
 * The public pages' footer, from store-footer.phtml (#17): the dealership's name and a line about it.
 */
final readonly class StoreFooter implements Component
{
    /**
     * @param string $name The dealership's name.
     * @param string $line What it says after the name.
     */
    public function __construct(
        public string $name,
        public string $line,
    ) {
    }
}
