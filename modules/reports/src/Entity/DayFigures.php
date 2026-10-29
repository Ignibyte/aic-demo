<?php

declare(strict_types=1);

namespace App\Reports\Entity;

/**
 * The digest's figures for one day (#20), as the `report_day` view counts them.
 */
final readonly class DayFigures
{
    /**
     * @param string $today      The day, `YYYY-MM-DD`.
     * @param int    $sales      The sales recorded for it.
     * @param int    $revenue    Their prices, whole dollars.
     * @param int    $leads      The leads that came in.
     * @param int    $inquiries  Of them, the questions.
     * @param int    $testDrives Of them, the test drive requests.
     * @param int    $newStock   The vehicles that came onto the lot.
     */
    public function __construct(
        public string $today,
        public int $sales,
        public int $revenue,
        public int $leads,
        public int $inquiries,
        public int $testDrives,
        public int $newStock,
    ) {
    }
}
