<?php

declare(strict_types=1);

namespace App\Holds\Entity;

/**
 * What a hold's transaction wrote (#20): `EntityStore::transaction()` runs a closure that returns nothing, so the write
 * holds the id here, and `HoldRepository` reads it once the transaction has committed, as sales' `SaleWrite` does.
 */
final class HoldWrite
{
    /**
     * The id written; 0 while nothing has been.
     */
    private int $id = 0;

    /**
     * @param int $id The hold the transaction placed or released.
     */
    public function hold(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return int The id held; 0 when the transaction wrote nothing.
     */
    public function written(): int
    {
        return $this->id;
    }
}
