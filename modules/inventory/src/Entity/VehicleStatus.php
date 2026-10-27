<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

/**
 * Where a vehicle stands, stored as its value in `vehicles.status`.
 */
enum VehicleStatus: string
{
    /** On the lot, for sale. */
    case InStock = 'in_stock';

    /** Kept for a buyer. */
    case OnHold = 'on_hold';

    /** Gone. */
    case Sold = 'sold';

    /**
     * @return string The status as staff read it.
     */
    public function label(): string
    {
        // if/return rather than match: elephc cannot lower a match whose arms are enum cases yet (AIC #164).
        if ($this === self::InStock) {
            return 'In stock';
        }

        if ($this === self::OnHold) {
            return 'On hold';
        }

        return 'Sold';
    }
}
