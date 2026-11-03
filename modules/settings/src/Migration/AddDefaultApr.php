<?php

declare(strict_types=1);

namespace App\Settings\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The dealership's default APR (#26): `dealership_profile.default_apr_bp`, in basis points (690 is 6.90%), which the
 * payment calculator on the car and vehicle pages starts at. AIC adds a column to a table that may have rows only as
 * nullable, so null is the standard 6.90%, which a profile saved before has.
 */
final readonly class AddDefaultApr implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_02_000001_add_default_apr';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->alter('dealership_profile')
            ->addColumn('default_apr_bp', $schema->types()->integer())
            ->nullable();
    }
}
