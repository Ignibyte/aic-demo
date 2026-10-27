<?php

declare(strict_types=1);

namespace App\Customers\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Ridgeway Motors' customers: an identity key, the name, the email (one row per email), the phone, address and
 * notes, and when the row was made and last changed; the name is indexed for the list's order.
 */
final readonly class CreateCustomersTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_27_000003_create_customers';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('customers')
            ->identity('id')
            ->string('name', 120)
            ->string('email', 254)
            ->string('phone', 30)
            ->string('address', 300)
            ->text('notes')
            ->timestampTz('created_at')
            ->timestampTz('updated_at')
            ->primary(['id'])
            ->unique(['email'])
            ->index(['name']);
    }
}
