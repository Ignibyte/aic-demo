<?php

declare(strict_types=1);

namespace App\Photos\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The `vehicle_photos` table as an entity type: this module's migration creates it, so this module alone names it.
 * The vehicle is an `Integer` field, not a `Reference`: a reference names the vehicles' entity type, and photos
 * cannot require inventory, which requires photos.
 */
final readonly class VehiclePhotoTable
{
    /**
     * @throws InvalidArgumentException When a field is malformed.
     *
     * @return EntityType The `vehicle_photos` table: one row per photo, with its vehicle, media type, size, hash and
     *                    day.
     */
    public function type(): EntityType
    {
        return new EntityType('vehicle_photos', 'id', [
            new Field('vehicle_id', FieldType::Integer),
            new Field('media_type', FieldType::Text),
            new Field('bytes', FieldType::Integer),
            new Field('sha256', FieldType::Text),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }
}
