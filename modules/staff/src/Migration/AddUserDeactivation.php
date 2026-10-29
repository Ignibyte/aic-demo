<?php

declare(strict_types=1);

namespace App\Staff\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * Lets an admin deactivate a member (#22): `users.deactivated_on`, the day it happened, null while they are active.
 * AIC adds a column to a table that has rows only as nullable, so null is the active state, which every row written
 * before has. And the database's today as a one-row view, `staff_today`, so the day is the database's and no PHP clock
 * comes in, as inventory's `inventory_today` does for its forms.
 */
final readonly class AddUserDeactivation implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_30_000001_add_user_deactivation';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     *
     * @throws InvalidArgumentException When the view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->alter('users')
            ->addColumn('deactivated_on', $schema->types()->date())
            ->nullable();
        $schema->view('staff_today', 'SELECT current_date AS today');
    }
}
