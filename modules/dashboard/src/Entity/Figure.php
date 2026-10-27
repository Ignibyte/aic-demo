<?php

declare(strict_types=1);

namespace App\Dashboard\Entity;

/**
 * One bar of a bar chart: what it measures and how much.
 */
final readonly class Figure
{
    /**
     * @param string $label  What it measures: a make, or a salesperson's name.
     * @param int    $amount How many vehicles, or whole dollars.
     */
    public function __construct(
        public string $label,
        public int $amount,
    ) {
    }
}
