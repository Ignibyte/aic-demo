<?php

declare(strict_types=1);

namespace App\Theme\Components\RangeLinks;

/**
 * One preset range's link: its name, the page over that range, and whether it is the range shown.
 */
final readonly class RangeLink
{
    /**
     * @param string $label  The range's name, such as "Last month".
     * @param string $href   The page over it.
     * @param bool   $chosen Whether it is the range shown.
     */
    public function __construct(
        public string $label,
        public string $href,
        public bool $chosen,
    ) {
    }
}
