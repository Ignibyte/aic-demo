<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

/**
 * Where on the lot a vehicle stands (#29), stored as its value in `vehicles.location`; a vehicle stored before the
 * column came has none (null) until a bulk move gives it one.
 */
enum VehicleLocation: string
{
    /** On the front row, by the road. */
    case FrontRow = 'front_row';

    /** Inside, in the showroom. */
    case Showroom = 'showroom';

    /** On the back lot. */
    case BackLot = 'back_lot';

    /** In the service bay. */
    case ServiceBay = 'service_bay';

    /**
     * @return string The location as staff read it.
     */
    public function label(): string
    {
        // if/return rather than match: elephc cannot lower a match whose arms are enum cases yet (AIC #164).
        if ($this === self::FrontRow) {
            return 'Front row';
        }

        if ($this === self::Showroom) {
            return 'Showroom';
        }

        if ($this === self::BackLot) {
            return 'Back lot';
        }

        return 'Service bay';
    }
}
