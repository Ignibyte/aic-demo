<?php

declare(strict_types=1);

namespace App\Customers\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The `customers` table as an entity type (AIC #185): customers' migration creates it, so this module alone names it.
 * Sales references a customer through it and reads its choices from it, and the dashboard counts it, so it is exported.
 */
final readonly class CustomerTable
{
    /**
     * @throws InvalidArgumentException When a field is malformed.
     *
     * @return EntityType The `customers` table: a customer's details, the email unique, with when the row was made and
     *                    changed.
     */
    public function type(): EntityType
    {
        return new EntityType('customers', 'id', [
            new Field('name', FieldType::Text),
            new Field('email', FieldType::Text),
            new Field('phone', FieldType::Text),
            new Field('address', FieldType::Text),
            new Field('notes', FieldType::Text),
            new Field('created_at', FieldType::CreatedAt),
            new Field('updated_at', FieldType::UpdatedAt),
        ]);
    }
}
