<?php

declare(strict_types=1);

namespace App\Theme\Components\IconEntry;

use AIC\Component\Contracts\Component;
use App\Theme\Components\Icon\Icon;

/**
 * One icon with its name, from icon-entry.phtml.
 */
final readonly class IconEntry implements Component
{
    /**
     * @param Icon   $icon  The icon.
     * @param string $label What it stands for.
     */
    public function __construct(
        public Icon $icon,
        public string $label,
    ) {
    }
}
