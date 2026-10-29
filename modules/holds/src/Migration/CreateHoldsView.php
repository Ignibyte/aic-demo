<?php

declare(strict_types=1);

namespace App\Holds\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * Each hold as staff read it (#20), a view, the one place the holds module writes SQL: its car, its customer and who
 * placed it, the day it was placed, when it expires (48 hours after, UTC, to the minute) and whether that time has
 * passed by the database's clock, so no time is worked out in PHP. A missing customer or member of staff reads as 0
 * and empty text.
 */
final readonly class CreateHoldsView implements Migration
{
    /**
     * When a hold placed at `h.created_at` expires.
     */
    private const string EXPIRES = "(h.created_at + interval '48 hours')";

    /**
     * The view's SELECT.
     */
    private const string STATES = 'SELECT h.id, h.vehicle_id, h.status, h.note,'
        . " trim(v.year || ' ' || v.make || ' ' || v.model || ' ' || v.trim) AS title, v.vin,"
        . " coalesce(c.name, '') AS customer_name,"
        . " coalesce(h.placed_by, 0) AS placed_by, coalesce(u.name, '') AS placer_name, coalesce(u.email, '') AS placer_email,"
        . " to_char(h.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD') AS placed_on,"
        . ' to_char(' . self::EXPIRES . " AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS expires,"
        . ' ' . self::EXPIRES . ' <= now() AS expired'
        . ' FROM holds h JOIN vehicles v ON v.id = h.vehicle_id'
        . ' LEFT JOIN customers c ON c.id = h.customer_id LEFT JOIN users u ON u.id = h.placed_by';

    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_29_000003_create_hold_states';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     *
     * @throws InvalidArgumentException When the view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->view('hold_states', self::STATES);
    }
}
