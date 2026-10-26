<?php

declare(strict_types=1);

namespace App\Theme\Components\Icon;

use AIC\Component\Contracts\Component;

/**
 * One 20px solid icon, from icon.phtml: inline SVG in the text's colour, hidden from assistive technology, drawn from
 * its paths. Heroicons gives each icon its paths.
 */
final readonly class Icon implements Component
{
    /**
     * @param list<IconPath> $paths The icon's paths, in drawing order.
     */
    public function __construct(
        public array $paths,
    ) {
    }
}
