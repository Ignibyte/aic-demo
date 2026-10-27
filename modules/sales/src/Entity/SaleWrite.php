<?php

declare(strict_types=1);

namespace App\Sales\Entity;

/**
 * What a sale's transaction wrote (#14): `EntityStore::transaction()` runs a closure that returns nothing, so the write
 * the closure makes (`SaleWriteRepository`) holds the id here, and `SaleRecordRepository` reads it once the
 * transaction has committed.
 */
final class SaleWrite
{
    /**
     * The id written; 0 while nothing has been.
     */
    private int $id = 0;

    /**
     * @param int $id The id the transaction wrote: a new sale's, or the vehicle a voided sale named.
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
