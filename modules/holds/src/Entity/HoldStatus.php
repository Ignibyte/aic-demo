<?php

declare(strict_types=1);

namespace App\Holds\Entity;

/**
 * Where a hold stands (#20), stored as its value in `holds.status`.
 */
enum HoldStatus: string
{
    /** Keeping its vehicle for its customer. */
    case Active = 'active';

    /** Ended when its 48 hours passed; the vehicle is for sale again. */
    case Released = 'released';

    /** Ended when its vehicle was sold to its customer. */
    case Sold = 'sold';
}
