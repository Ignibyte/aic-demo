<?php

declare(strict_types=1);

namespace App\Settings\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The `password_link_states` view (#22): each password link with whether its 24 hours have passed, by the database's
 * `now()`, so the set-password page works out no time in PHP.
 */
final readonly class CreatePasswordLinkStatesView implements Migration
{
    /**
     * Each link, and whether it is past its 24 hours.
     */
    private const string STATES = 'SELECT id, user_id, used,'
        . " (created_at < now() - interval '24 hours') AS expired FROM password_links";

    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_30_000004_create_password_link_states';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     *
     * @throws InvalidArgumentException When the view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->view('password_link_states', self::STATES);
    }
}
