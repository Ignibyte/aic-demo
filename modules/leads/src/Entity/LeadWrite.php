<?php

declare(strict_types=1);

namespace App\Leads\Entity;

/**
 * What recording a lead wrote (#18): `EntityStore::transaction()` runs a closure that returns nothing, so the new
 * lead's id comes back through this, as sales' `SaleWrite` does it.
 */
final class LeadWrite
{
    /**
     * The new lead's id; 0 until it is written.
     */
    private int $id = 0;

    /**
     * @param int $id The new lead's id.
     */
    public function hold(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return int The new lead's id; 0 when nothing was written.
     */
    public function written(): int
    {
        return $this->id;
    }
}
