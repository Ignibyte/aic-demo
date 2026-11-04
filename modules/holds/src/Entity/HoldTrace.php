<?php

declare(strict_types=1);

namespace App\Holds\Entity;

/**
 * A hold as a vehicle's page shows it (#31): the day it was placed, its customer, who placed it, its state as staff
 * read it and when it expires. Exported, so the panels module lists a vehicle's holds without reading the holds
 * tables itself.
 */
final readonly class HoldTrace
{
    /**
     * @param string $placedOn     The day it was placed, `YYYY-MM-DD`, UTC.
     * @param string $customerName Its customer's name; empty when the customer is gone.
     * @param string $placerName   Who placed it; empty when they are gone.
     * @param string $state        `Active`, `Expired` (active, its time passed), `Released` or `Sold`.
     * @param string $expires      When it expires, `YYYY-MM-DD HH:MM`, UTC.
     */
    public function __construct(
        public string $placedOn,
        public string $customerName,
        public string $placerName,
        public string $state,
        public string $expires,
    ) {
    }
}
