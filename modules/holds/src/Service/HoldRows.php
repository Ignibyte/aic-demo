<?php

declare(strict_types=1);

namespace App\Holds\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Holds\Entity\Hold;

/**
 * Reads a row of the `hold_states` view into a `Hold` (#20).
 */
final readonly class HoldRows
{
    /**
     * @param Row $row A row of `hold_states`.
     *
     * @throws QueryException When a column is missing.
     *
     * @return Hold The hold.
     */
    public function hold(Row $row): Hold
    {
        return new Hold(
            $row->int('id'),
            $row->int('vehicle_id'),
            $row->string('title'),
            $row->string('vin'),
            $row->string('customer_name'),
            $row->int('placed_by'),
            $row->string('placer_name'),
            $row->string('placer_email'),
            $row->string('note'),
            $row->string('placed_on'),
            $row->string('expires'),
        );
    }
}
