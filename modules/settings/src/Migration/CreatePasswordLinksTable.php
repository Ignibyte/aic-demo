<?php

declare(strict_types=1);

namespace App\Settings\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The password links an admin has had mailed (#22): whose, whether it was used (or voided by a newer one), and when it
 * was made, from which the `password_link_states` view counts its 24 hours. A member's links go with them.
 */
final readonly class CreatePasswordLinksTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_30_000003_create_password_links';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('password_links')
            ->identity('id')
            ->bigInteger('user_id')
            ->boolean('used')
            ->timestampTz('created_at')
            ->primary(['id'])
            ->index(['user_id'])
            ->foreignKey('user_id', 'users', 'id', OnDelete::Cascade);
    }
}
