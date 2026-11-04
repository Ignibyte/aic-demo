<?php

declare(strict_types=1);

namespace App\Holds\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Holds\Entity\Hold;
use App\Holds\Entity\HoldStatus;
use App\Holds\Entity\HoldTrace;

/**
 * Reads a row of the `hold_states` view into a `Hold` (#20), or into a `HoldTrace` for a vehicle's page (#31).
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

    /**
     * @param Row $row A row of `hold_states`.
     *
     * @throws QueryException When a column is missing.
     *
     * @return HoldTrace The hold as a vehicle's page shows it, its state from its status and whether its time passed.
     */
    public function trace(Row $row): HoldTrace
    {
        return new HoldTrace(
            $row->string('placed_on'),
            $row->string('customer_name'),
            $row->string('placer_name'),
            $this->state($row->string('status'), $row->bool('expired')),
            $row->string('expires'),
        );
    }

    /**
     * @param string $status  A hold's stored status.
     * @param bool   $expired Whether its time has passed.
     *
     * @return string How staff read it: `Released`, `Sold`, `Expired` for an active hold past its time, else `Active`.
     */
    private function state(string $status, bool $expired): string
    {
        // if/return rather than a match over the cases: elephc's build of the site fails on one (AIC #64, #164).
        if ($status === HoldStatus::Released->value) {
            return 'Released';
        }

        if ($status === HoldStatus::Sold->value) {
            return 'Sold';
        }

        if ($expired) {
            return 'Expired';
        }

        return 'Active';
    }
}
