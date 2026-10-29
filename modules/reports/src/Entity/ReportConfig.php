<?php

declare(strict_types=1);

namespace App\Reports\Entity;

use InvalidArgumentException;

/**
 * The reports' settings (#20), which config/reports.php returns: how many days on the lot make a vehicle aged stock.
 */
final readonly class ReportConfig
{
    /**
     * @param int $agedStockDays A vehicle not sold that has been on the lot more days than this is aged stock.
     *
     * @throws InvalidArgumentException When it is below 1.
     */
    public function __construct(
        public int $agedStockDays,
    ) {
        if ($agedStockDays < 1) {
            throw new InvalidArgumentException('ReportConfig: agedStockDays is 1 or more.');
        }
    }
}
