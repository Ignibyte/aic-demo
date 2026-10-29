<?php

declare(strict_types=1);

namespace App\Holds\Entity;

/**
 * A hold as staff read it (#20), from the `hold_states` view: its car, its customer, who placed it, the day it was
 * placed and when it expires.
 */
final readonly class Hold
{
    /**
     * Where staff pages for holds live.
     */
    public const string PAGES = '/app/holds';

    /**
     * @param int    $id           Its id.
     * @param int    $vehicleId    Its vehicle's id.
     * @param string $title        Its car: year, make, model and trim.
     * @param string $vin          Its car's VIN.
     * @param string $customerName Its customer's name; empty when the customer is gone.
     * @param int    $placedBy     The id of the member of staff who placed it; 0 when they are gone.
     * @param string $placerName   Their name; empty when they are gone.
     * @param string $placerEmail  Their login; empty when they are gone.
     * @param string $note         Why it was placed.
     * @param string $placedOn     The day it was placed, `YYYY-MM-DD`, UTC.
     * @param string $expires      When it expires, `YYYY-MM-DD HH:MM`, UTC.
     */
    public function __construct(
        public int $id,
        public int $vehicleId,
        public string $title,
        public string $vin,
        public string $customerName,
        public int $placedBy,
        public string $placerName,
        public string $placerEmail,
        public string $note,
        public string $placedOn,
        public string $expires,
    ) {
    }

    /**
     * @return string The car's inventory page.
     */
    public function carPath(): string
    {
        return '/app/inventory/' . $this->vin;
    }
}
