<?php

declare(strict_types=1);

namespace App\Holds\Entity;

/**
 * A hold to place (#20), checked: the vehicle, the customer and the member of staff by their ids, and the note.
 */
final readonly class HoldDetails
{
    /**
     * @param int    $vehicleId  The vehicle's id.
     * @param int    $customerId The customer's id.
     * @param int    $placedBy   The id of the member of staff placing it.
     * @param string $note       Why, trimmed.
     */
    public function __construct(
        public int $vehicleId,
        public int $customerId,
        public int $placedBy,
        public string $note,
    ) {
    }
}
