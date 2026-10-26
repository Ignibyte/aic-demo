<?php

declare(strict_types=1);

namespace App\Theme\Components\StatTiles;

/**
 * One figure: its title, its value and a line under it.
 */
final readonly class StatTile
{
    /**
     * @param string $id          The value's id.
     * @param string $title       What it counts.
     * @param string $value       The figure, formatted.
     * @param string $description A line under it.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $value,
        public string $description,
    ) {
    }
}
