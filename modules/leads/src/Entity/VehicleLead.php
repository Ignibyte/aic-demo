<?php

declare(strict_types=1);

namespace App\Leads\Entity;

/**
 * A lead as a vehicle's page shows it (#31): its id and staff page, the day it came, the visitor's name, its kind and
 * stage as staff read them, and its salesperson's name, empty while it is unassigned. Exported, so the panels module
 * lists a vehicle's leads without reading the leads table itself.
 */
final readonly class VehicleLead
{
    /**
     * @param int    $id         Its id.
     * @param string $receivedOn The day it came, `YYYY-MM-DD`.
     * @param string $name       The visitor's name.
     * @param string $kind       Its kind's label (`Question`, `Test drive`).
     * @param string $stage      Its stage's label (`New` to `Lost`).
     * @param string $seller     Its salesperson's name; empty while it is unassigned.
     */
    public function __construct(
        public int $id,
        public string $receivedOn,
        public string $name,
        public string $kind,
        public string $stage,
        public string $seller,
    ) {
    }

    /**
     * @return string Its staff page, `/app/leads/{id}`.
     */
    public function path(): string
    {
        return Lead::PAGES . '/' . $this->id;
    }
}
