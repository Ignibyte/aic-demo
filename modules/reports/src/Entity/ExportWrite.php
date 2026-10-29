<?php

declare(strict_types=1);

namespace App\Reports\Entity;

/**
 * What an export's transaction wrote (#21): `EntityStore::transaction()` runs a closure that returns nothing, so the
 * write holds the new export's id here, and `ExportRepository` reads it once the transaction has committed, as holds'
 * `HoldWrite` does.
 */
final class ExportWrite
{
    /**
     * The id written; 0 while nothing has been.
     */
    private int $id = 0;

    /**
     * @param int $id The export the transaction stored.
     */
    public function export(int $id): void
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
