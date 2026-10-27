<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use function number_format;

/**
 * How the dashboard writes its numbers: a count with its noun, and whole dollars.
 */
final readonly class DashboardWords
{
    /**
     * @param int    $count    How many.
     * @param string $singular The noun for one (`sale`).
     * @param string $plural   The noun for any other count (`sales`).
     *
     * @return string The count and its noun: `1 sale`, `1,204 sales`.
     */
    public function count(int $count, string $singular, string $plural): string
    {
        return number_format($count) . ' ' . ($count === 1 ? $singular : $plural);
    }

    /**
     * @param int $dollars Whole dollars, from 0.
     *
     * @return string As staff read them: `$318,450`.
     */
    public function money(int $dollars): string
    {
        return '$' . number_format($dollars);
    }
}
