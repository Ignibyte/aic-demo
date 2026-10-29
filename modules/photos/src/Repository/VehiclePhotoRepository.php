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
 * A vehicle's photo rows through the entity store: read in the order they were added, counted, added and deleted; and,
 * across vehicles, the rows that hold one file's bytes and the vehicles with any photo.
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
     * @param string $sha256 A photo's SHA-256, in hex.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int How many photos, of any vehicle, hold those bytes and so their file.
     */
    public function holding(string $sha256): int
    {
        return $this->entityStore->query($this->vehiclePhotoTable->type())
            ->where('sha256', $sha256)
            ->totals()
            ->count();
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<int> The id of every vehicle with a photo, smallest first.
     */
    public function vehicleIds(): array
    {
        $rows = $this->entityStore->query($this->vehiclePhotoTable->type())
            ->orderBy('vehicle_id')
            ->rows();
        $vehicleIds = [];
        $last = 0;

        foreach ($rows as $row) {
            $vehicleId = self::vehicleId($row);

            if ($vehicleId === $last) {
                continue;
            }

            $vehicleIds[] = $vehicleId;
            $last = $vehicleId;
        }

        return $vehicleIds;
    }

    /**
     * @param int    $vehicleId A vehicle's id.
     * @param string $mediaType The photo's media type.
     * @param int    $bytes     Its size.
     * @param string $sha256    The SHA-256 of its bytes, in hex.
     *
     * @throws QueryException           When the insert fails, such as for a vehicle deleted since it was read.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return int The new photo's id.
     */
    public function add(int $vehicleId, string $mediaType, int $bytes, string $sha256): int
    {
        return $this->entityStore->insert($this->vehiclePhotoTable->type(), [
            'bytes' => $bytes,
            'media_type' => $mediaType,
            'sha256' => $sha256,
            'vehicle_id' => $vehicleId,
        ]);
    }

    /**
     * @param int    $vehicleId A vehicle's id.
     * @param int    $photoId   One of its photos' id.
     * @param string $sha256    The SHA-256 of the photo's file, in hex, for a row from before the hash was kept at insert.
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

    /**
     * @param Row $row A `vehicle_photos` row.
     *
     * @throws QueryException When the column is missing or not an integer.
     *
     * @return int Its vehicle's id.
     */
    private static function vehicleId(Row $row): int
    {
        return $row->int('vehicle_id');
    }
}
