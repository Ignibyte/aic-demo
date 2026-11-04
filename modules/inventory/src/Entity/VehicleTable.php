<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The `vehicles` table as an entity type (AIC #185): inventory's migration creates it, so this module alone names it.
 * Sales references a vehicle through it and reads its choices from it, and the dashboard counts it, so it is exported.
 * Its location (#29) may be null: a vehicle stored before the column came has none. It names `vehicle_prices` too (#31),
 * the list price changes inventory's saves record, so one class names both of inventory's tables.
 */
final readonly class VehicleTable
{
    /**
     * @throws InvalidArgumentException When a field is malformed.
     *
     * @return EntityType The `vehicle_prices` table: one row per change of a vehicle's list price, the price it had and
     *                    the price it took, on the database's day; its rows go with their vehicle.
     */
    public function prices(): EntityType
    {
        return new EntityType('vehicle_prices', 'id', [
            new Field('vehicle_id', FieldType::Reference, false, $this->type()),
            new Field('from_price', FieldType::Integer),
            new Field('to_price', FieldType::Integer),
            new Field('changed_on', FieldType::Date),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a field is malformed.
     *
     * @return EntityType The `vehicles` table: one row per VIN, which it holds unique, with when it was made and changed.
     */
    public function type(): EntityType
    {
        return new EntityType('vehicles', 'id', [
            new Field('vin', FieldType::Text),
            new Field('year', FieldType::Integer),
            new Field('make', FieldType::Text),
            new Field('model', FieldType::Text),
            new Field('trim', FieldType::Text),
            new Field('body_style', FieldType::Text),
            new Field('mileage', FieldType::Integer),
            new Field('exterior_colour', FieldType::Text),
            new Field('list_price', FieldType::Integer),
            new Field('cost', FieldType::Integer),
            new Field('status', FieldType::Text),
            new Field('added_on', FieldType::Date),
            new Field('location', FieldType::Text, true),
            new Field('created_at', FieldType::CreatedAt),
            new Field('updated_at', FieldType::UpdatedAt),
        ]);
    }
}
