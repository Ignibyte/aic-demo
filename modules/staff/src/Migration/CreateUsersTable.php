<?php

declare(strict_types=1);

namespace App\Staff\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The staff who sign in: an identity key, the email they sign in with (one account per email), their name, their
 * role (Role's values) and a pgcrypto bcrypt hash.
 */
final readonly class CreateUsersTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_27_000001_create_users';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('users')
            ->identity('id')
            ->string('email', 254)
            ->string('name', 100)
            ->string('role', 20)
            ->text('password_hash')
            ->timestampTz('created_at')
            ->primary(['id'])
            ->unique(['email']);
    }
}
