<?php

declare(strict_types=1);

namespace App\Inventory\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The database's today as a one-row view (#14): the vehicle form's first date and its last model year, the sale form's
 * day and the dashboard's version count from it. The entity API has no query for `current_date`, and a view is where a
 * module writes the SQL it needs, so the date stays the database's and no PHP clock comes in.
 */
final readonly class CreateTodayView implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_27_000005_create_inventory_today';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     *
     * @throws InvalidArgumentException When the view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->view('inventory_today', 'SELECT current_date AS today');
    }
}
