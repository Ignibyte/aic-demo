<?php

declare(strict_types=1);

namespace App\Theme\Layouts;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;

/**
 * The brochure layout's data (#44): a car's one-page brochure, which a job keeps to be printed, in its `content` region
 * alone. It has no header, drawer or docks, and no staff block places itself in it.
 */
final readonly class BrochurePage implements Component
{
    /**
     * @param Region $content The brochure's sheet.
     */
    public function __construct(
        public Region $content,
    ) {
    }
}
