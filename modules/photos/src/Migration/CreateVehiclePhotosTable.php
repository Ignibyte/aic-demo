<?php

declare(strict_types=1);

namespace App\Photos\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * A vehicle's photos (#16): an identity key, the vehicle it shows, its media type as `UploadCheck` told it, its size
 * in bytes, the SHA-256 of its bytes, and when it was added. The foreign key cascades, so deleting a vehicle deletes
 * its rows; `PhotoFiles` deletes their files. It names inventory's `vehicles`, which an earlier migration creates.
 */
final readonly class CreateVehiclePhotosTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_28_000001_create_vehicle_photos';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('vehicle_photos')
            ->identity('id')
            ->bigInteger('vehicle_id')
            ->string('media_type', 20)
            ->integer('bytes')
            ->string('sha256', 64)
            ->timestampTz('created_at')
            ->primary(['id'])
            ->index(['vehicle_id'])
            ->foreignKey('vehicle_id', 'vehicles', 'id', OnDelete::Cascade);
    }
}
