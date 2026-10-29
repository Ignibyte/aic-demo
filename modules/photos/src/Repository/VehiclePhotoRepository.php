<?php

declare(strict_types=1);

namespace App\Photos\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Entity\VehiclePhotoTable;
use InvalidArgumentException;

use function count;

/**
 * A vehicle's photo rows through the entity store: read in the order they were added, counted, added and deleted.
 */
final readonly class VehiclePhotoRepository
{
    /**
     * @param EntityStore       $entityStore       The entity store.
     * @param VehiclePhotoTable $vehiclePhotoTable The `vehicle_photos` entity type.
     */
    public function __construct(
        private EntityStore $entityStore,
        private VehiclePhotoTable $vehiclePhotoTable = new VehiclePhotoTable(),
    ) {
    }

    /**
     * @param int $vehicleId A vehicle's id.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<VehiclePhoto> Its photos, the first added first, each with its position from 1.
     */
    public function photos(int $vehicleId): array
    {
        $rows = $this->entityStore->query($this->vehiclePhotoTable->type())
            ->where('vehicle_id', $vehicleId)
            ->orderBy('id')
            ->rows();
        $photos = [];

        foreach ($rows as $row) {
            $photos[] = $this->photo($row, count($photos) + 1);
        }

        return $photos;
    }

    /**
     * @param int $vehicleId A vehicle's id.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int How many photos it has.
     */
    public function count(int $vehicleId): int
    {
        return $this->entityStore->query($this->vehiclePhotoTable->type())
            ->where('vehicle_id', $vehicleId)
            ->totals()
            ->count();
    }

    /**
     * @param int    $vehicleId A vehicle's id.
     * @param string $mediaType The photo's media type.
     * @param int    $bytes     Its size.
     *
     * @throws QueryException           When the insert fails, such as for a vehicle deleted since it was read.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return int The new photo's id; its hash is empty until `hashed()` records it, once its file is in place.
     */
    public function add(int $vehicleId, string $mediaType, int $bytes): int
    {
        return $this->entityStore->insert($this->vehiclePhotoTable->type(), [
            'bytes' => $bytes,
            'media_type' => $mediaType,
            'sha256' => '',
            'vehicle_id' => $vehicleId,
        ]);
    }

    /**
     * @param int    $vehicleId A vehicle's id.
     * @param int    $photoId   One of its photos' id.
     * @param string $sha256    The SHA-256 of the photo's file, in hex.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When the value does not fit its field.
     */
    public function hashed(int $vehicleId, int $photoId, string $sha256): void
    {
        $this->entityStore->query($this->vehiclePhotoTable->type())
            ->where('vehicle_id', $vehicleId)
            ->where('id', $photoId)
            ->changes()
            ->update(['sha256' => $sha256]);
    }

    /**
     * @param int $vehicleId A vehicle's id.
     * @param int $photoId   One of its photos' id.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return bool Whether it was deleted; false when the vehicle has no photo with that id.
     */
    public function delete(int $vehicleId, int $photoId): bool
    {
        return $this->entityStore->query($this->vehiclePhotoTable->type())
            ->where('vehicle_id', $vehicleId)
            ->where('id', $photoId)
            ->changes()
            ->delete() === 1;
    }

    /**
     * Reads a row through a parameter typed `Row`, as `VehicleRows` does: elephc does not narrow a loop's element from
     * `rows()` to a `Row`, and its build of the site refused `string()` called on it. No AIC ticket carries this yet.
     *
     * @param Row $row      A `vehicle_photos` row.
     * @param int $position Its place among its vehicle's photos, from 1.
     *
     * @throws QueryException When a column is missing or not of its type.
     *
     * @return VehiclePhoto The photo it records.
     */
    private function photo(Row $row, int $position): VehiclePhoto
    {
        return new VehiclePhoto($row->int('id'), $position, $row->string('media_type'), $row->string('sha256'));
    }
}
