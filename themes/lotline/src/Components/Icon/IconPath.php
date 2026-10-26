<?php

declare(strict_types=1);

namespace App\Theme\Components\Icon;

/**
 * One path of an icon: its geometry, and whether it fills by the even-odd rule (`fill-rule` and `clip-rule`).
 */
final readonly class IconPath
{
    /**
     * @param string $geometry The path's `d` data.
     * @param bool   $evenOdd  Whether it fills and clips by the even-odd rule.
     */
    public function __construct(
        public string $geometry,
        public bool $evenOdd,
    ) {
    }
}
