<?php

declare(strict_types=1);

namespace App\Staff\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The `users` table as an entity type (AIC #185): staff's migration creates it, so this module alone names it. Sales
 * references a member through it and reads its sellers and its form's staff from it, so it is exported.
 */
final readonly class StaffTable
{
    /**
     * @throws InvalidArgumentException When a field is malformed.
     *
     * @return EntityType The `users` table: a member's email, which it holds unique, name, role, password hash and the
     *                    day they were deactivated, null while they are active (#22).
     */
    public function type(): EntityType
    {
        return new EntityType('users', 'id', [
            new Field('email', FieldType::Text),
            new Field('name', FieldType::Text),
            new Field('role', FieldType::Text),
            new Field('password_hash', FieldType::Text),
            new Field('deactivated_on', FieldType::Date, true),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }

    /**
     * @throws InvalidArgumentException When the view's name is malformed.
     *
     * @return EntityType The `staff_today` view: the database's today, one row (#22).
     */
    public function today(): EntityType
    {
        return new EntityType('staff_today', 'today', [], true);
    }
}
