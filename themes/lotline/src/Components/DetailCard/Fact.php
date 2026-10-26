<?php

declare(strict_types=1);

namespace App\Theme\Components\DetailCard;

/**
 * One fact on a record's card: its name and its value.
 */
final readonly class Fact
{
    /**
     * @param string $term   What it is.
     * @param string $detail Its value.
     * @param bool   $mono   Whether the value is set monospace (an identifier).
     */
    public function __construct(
        public string $term,
        public string $detail,
        public bool $mono,
    ) {
    }
}
