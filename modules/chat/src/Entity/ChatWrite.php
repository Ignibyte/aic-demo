<?php

declare(strict_types=1);

namespace App\Chat\Entity;

/**
 * What a chat's transaction wrote (#32): `EntityStore::transaction()` runs a closure that returns nothing, so the new
 * chat's id, or the seq of the message added, comes back through this, as leads' `LeadWrite` does it.
 */
final class ChatWrite
{
    /**
     * The new chat's id or the message's seq; 0 until something is written.
     */
    private int $written = 0;

    /**
     * @param int $written The new chat's id or the message's seq.
     */
    public function hold(int $written): void
    {
        $this->written = $written;
    }

    /**
     * @return int The new chat's id or the message's seq; 0 when nothing was written.
     */
    public function written(): int
    {
        return $this->written;
    }
}
