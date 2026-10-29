<?php

declare(strict_types=1);

namespace App\Settings\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The `dealership_profile` table as an entity type (#22): this module's migration creates it, so it alone names it.
 */
final readonly class ProfileTable
{
    /**
     * The one row's slot, on which a save upserts.
     */
    public const string SLOT = 'main';

    /**
     * @throws InvalidArgumentException When a field is malformed.
     *
     * @return EntityType The profile's one row.
     */
    public function type(): EntityType
    {
        return new EntityType('dealership_profile', 'id', [
            new Field('slot', FieldType::Text),
            new Field('name', FieldType::Text),
            new Field('address', FieldType::Text),
            new Field('phone', FieldType::Text),
            new Field('opens_at', FieldType::Text),
            new Field('closes_at', FieldType::Text),
            new Field('open_days', FieldType::Text),
            new Field('aged_stock_days', FieldType::Integer),
            new Field('updated_at', FieldType::UpdatedAt),
        ]);
    }
}
