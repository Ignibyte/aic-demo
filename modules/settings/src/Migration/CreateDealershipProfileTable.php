<?php

declare(strict_types=1);

namespace App\Settings\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The dealership's profile (#22): one row, which `slot` (always `main`, held unique) lets a save upsert. Its name, the
 * address and phone the public footer shows, the opening and closing time and the open days (ISO weekday digits,
 * `123456` for Monday to Saturday) a test drive is held to, and the days on the lot after which a vehicle is aged
 * stock. Until an admin saves it the table is empty and every reader gets the defaults.
 */
final readonly class CreateDealershipProfileTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_30_000002_create_dealership_profile';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('dealership_profile')
            ->identity('id')
            ->string('slot', 10)
            ->string('name', 100)
            ->string('address', 200)
            ->string('phone', 30)
            ->string('opens_at', 5)
            ->string('closes_at', 5)
            ->string('open_days', 7)
            ->integer('aged_stock_days')
            ->timestampTz('updated_at')
            ->primary(['id'])
            ->unique(['slot']);
    }
}
