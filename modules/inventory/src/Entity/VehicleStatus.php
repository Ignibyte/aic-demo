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
        return match ($this) {
            self::InStock => 'In stock',
            self::OnHold => 'On hold',
            self::Sold => 'Sold',
        };
    }
}
