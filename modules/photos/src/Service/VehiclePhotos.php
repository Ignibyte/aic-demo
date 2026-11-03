<?php

declare(strict_types=1);

namespace App\Photos\Service;

use AIC\Controller\Domain\FileDownload;
use AIC\Entity\Application\EntityStore;
use AIC\Form\Application\UploadCheck;
use AIC\Form\Domain\UploadKind;
use AIC\Form\Exception\RefusedUpload;
use AIC\Routing\Domain\RouteNotFoundException;
use AIC\Storage\Application\PublicFiles;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Repository\VehiclePhotoRepository;
use App\Staff\Entity\Role;
use App\Staff\Service\StaffSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

use function hash;
use function strlen;

/**
 * A vehicle's photos, which inventory's pages show and change by the vehicle's id: read, added from a posted file,
 * removed, forgotten with a deleted vehicle, and served. Their files are kept in AIC's public files (#23), each under a
 * key named by its bytes, so two rows with the same bytes share one file, which goes when the last of them does. Who
 * may change them is a sales manager or an admin.
 */
final readonly class VehiclePhotos
{
    /**
     * The most photos one vehicle holds.
     */
    public const int MOST = 12;

    /**
     * The largest photo accepted, in bytes.
     */
    public const int BYTES = 8_000_000;

    /**
     * The posted file's field.
     */
    public const string FIELD = 'photo';

    /**
     * What an upload to a full vehicle is told.
     */
    private const string FULL = 'A vehicle holds at most 12 photos. Delete one to add another.';

    /**
     * The photos' rows.
     */
    private VehiclePhotoRepository $vehiclePhotoRepository;

    /**
     * @param EntityStore  $entityStore  The entity store.
     * @param PublicFiles  $publicFiles  AIC's public files, where the photos' files are kept.
     * @param StaffSession $staffSession Who is signed in.
     */
    public function __construct(
        EntityStore $entityStore,
        private PublicFiles $publicFiles,
        private StaffSession $staffSession = new StaffSession(),
    ) {
        $this->vehiclePhotoRepository = new VehiclePhotoRepository($entityStore);
    }

    /**
     * @param int $vehicleId A vehicle's id.
     *
     * @throws LogicException   When their entity type is malformed.
     * @throws RuntimeException When the photos cannot be read.
     *
     * @return list<VehiclePhoto> Its photos, the first added first.
     */
    public function photos(int $vehicleId): array
    {
        return $this->vehiclePhotoRepository->photos($vehicleId);
    }

    /**
     * @param list<int> $vehicleIds Vehicles' ids.
     *
     * @throws LogicException   When their entity type is malformed.
     * @throws RuntimeException When the photos cannot be read.
     *
     * @return array<int, list<VehiclePhoto>> Each vehicle's photos by its id, the first added first, read at once (#25);
     *                                        a vehicle with none has no entry.
     */
    public function photosOf(array $vehicleIds): array
    {
        return $this->vehiclePhotoRepository->photosOf($vehicleIds);
    }

    /**
     * @param int $vehicleId A vehicle's id.
     * @param int $position  A position among its photos, from 1.
     *
     * @throws LogicException   When their entity type is malformed.
     * @throws RuntimeException When the photos cannot be read.
     *
     * @return VehiclePhoto|null The photo there; null when there is none.
     */
    public function photo(int $vehicleId, int $position): ?VehiclePhoto
    {
        foreach ($this->photos($vehicleId) as $vehiclePhoto) {
            if ($vehiclePhoto->position === $position) {
                return $vehiclePhoto;
            }
        }

        return null;
    }

    /**
     * Keeps the request's `photo` when the vehicle has room for it and AIC's `UploadCheck` accepts its content.
     *
     * @param ServerRequestInterface $serverRequest A posted multipart form.
     * @param int                    $vehicleId     The vehicle it is for.
     *
     * @throws LogicException   When a value does not fit its field.
     * @throws RuntimeException When the photos cannot be counted, or the row written, or the upload read or its file
     *                          written.
     *
     * @return string Why it was refused; empty when it was kept.
     */
    public function add(ServerRequestInterface $serverRequest, int $vehicleId): string
    {
        $uploadedFile = $serverRequest->getUploadedFiles()[self::FIELD] ?? null;

        if (!$uploadedFile instanceof UploadedFileInterface) {
            return 'Choose a photo to add.';
        }

        if ($this->vehiclePhotoRepository->count($vehicleId) >= self::MOST) {
            return self::FULL;
        }

        try {
            $extension = $this->uploadCheck()->kind($uploadedFile)->extension;
        } catch (RefusedUpload $refusedUpload) {
            return $refusedUpload->getMessage();
        }

        return $this->keep($uploadedFile, $vehicleId, $this->mediaType($extension))
            ? ''
            : self::FULL;
    }

    /**
     * @param int          $vehicleId    A vehicle's id.
     * @param VehiclePhoto $vehiclePhoto One of its photos.
     *
     * @throws LogicException   When its entity type is malformed.
     * @throws RuntimeException When the row cannot be deleted or the others holding its bytes counted.
     *
     * @return bool Whether it was deleted, its file with it when no other photo holds the same bytes; false when it was
     *              gone already.
     */
    public function remove(int $vehicleId, VehiclePhoto $vehiclePhoto): bool
    {
        if (!$this->vehiclePhotoRepository->delete($vehicleId, $vehiclePhoto->id)) {
            return false;
        }

        $this->release($vehiclePhoto);

        return true;
    }

    /**
     * Removes the files of a deleted vehicle's photos, read before its delete; their rows went with it, by the foreign
     * key's cascade. A file another vehicle's photo holds stays.
     *
     * @param list<VehiclePhoto> $vehiclePhotos The deleted vehicle's photos.
     *
     * @throws LogicException   When their entity type is malformed.
     * @throws RuntimeException When the photos holding a file's bytes cannot be counted.
     */
    public function forget(array $vehiclePhotos): void
    {
        foreach ($vehiclePhotos as $vehiclePhoto) {
            $this->release($vehiclePhoto);
        }
    }

    /**
     * @param ServerRequestInterface $serverRequest The request for the photo: its range and conditions.
     * @param int                    $vehicleId     The vehicle's id; 0 for none.
     * @param int                    $position      The photo's position, from 1.
     *
     * @throws LogicException   When their entity type is malformed.
     * @throws RuntimeException When the photos cannot be read, or the vehicle has no photo there or its file is gone
     *                          (`RouteNotFoundException`, which AIC answers 404).
     *
     * @return FileDownload The photo, inline, with its media type, from AIC's public files; AIC sends its ETag and
     *                      honours the request's conditions and range.
     */
    public function download(ServerRequestInterface $serverRequest, int $vehicleId, int $position): FileDownload
    {
        $vehiclePhoto = $vehicleId > 0
            ? $this->photo($vehicleId, $position)
            : null;

        if (!$vehiclePhoto instanceof VehiclePhoto) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        $path = $this->publicFiles->served($vehiclePhoto->key());

        if ($path === null) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        return new FileDownload($path, $vehiclePhoto->mediaType, '', $serverRequest);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws LogicException   When the request carries no session.
     * @throws RuntimeException When the kept role is no role.
     *
     * @return bool Whether the signed-in member may add and delete photos: a sales manager or an admin.
     */
    public function mayChange(ServerRequestInterface $serverRequest): bool
    {
        $staffMember = $this->staffSession->member($serverRequest);

        // A comparison rather than match: elephc cannot lower a match whose arms are enum cases yet (AIC #164).
        return $staffMember !== null && $staffMember->role !== Role::Salesperson;
    }

    /**
     * The upload is read once, and its hash taken from those bytes, which name its file. The row comes first; the
     * vehicle's photos are counted again after it: two uploads sent together past the count before it may both have
     * written, and a row that takes the vehicle over the limit is deleted, so at most `MOST` stay whatever the timing.
     * Then the file is written, over any file with the same bytes; when that fails, the row goes too.
     *
     * @param UploadedFileInterface $uploadedFile A checked upload.
     * @param int                   $vehicleId    The vehicle it is for.
     * @param string                $mediaType    Its media type.
     *
     * @throws LogicException   When a value does not fit its field.
     * @throws RuntimeException When a row cannot be written or counted, or the upload read or its file written.
     *
     * @return bool Whether it was kept; false when the vehicle was full by the time its row was written.
     */
    private function keep(UploadedFileInterface $uploadedFile, int $vehicleId, string $mediaType): bool
    {
        $contents = (string) $uploadedFile->getStream();
        $sha256 = hash('sha256', $contents);
        $photoId = $this->vehiclePhotoRepository->add($vehicleId, $mediaType, strlen($contents), $sha256);

        if ($this->vehiclePhotoRepository->count($vehicleId) > self::MOST) {
            $this->vehiclePhotoRepository->delete($vehicleId, $photoId);

            return false;
        }

        try {
            $this->publicFiles->put((new VehiclePhoto($photoId, 0, $mediaType, $sha256))->key(), $contents);
        } catch (RuntimeException $runtimeException) {
            $this->vehiclePhotoRepository->delete($vehicleId, $photoId);

            throw $runtimeException;
        }

        return true;
    }

    /**
     * @param VehiclePhoto $vehiclePhoto A photo whose row is gone.
     *
     * @throws LogicException   When the entity type is malformed.
     * @throws RuntimeException When the photos holding its bytes cannot be counted.
     */
    private function release(VehiclePhoto $vehiclePhoto): void
    {
        if ($this->vehiclePhotoRepository->holding($vehiclePhoto->sha256) > 0) {
            return;
        }

        $this->publicFiles->delete($vehiclePhoto->key());
    }

    /**
     * @return UploadCheck AIC's check of a photo's content and size.
     */
    private function uploadCheck(): UploadCheck
    {
        return new UploadCheck(self::BYTES, [UploadKind::Jpeg, UploadKind::Png, UploadKind::WebP]);
    }

    /**
     * @param string $extension The extension `UploadCheck` found the upload stored under (`png`).
     *
     * @return string Its media type.
     */
    private function mediaType(string $extension): string
    {
        if ($extension === 'png') {
            return 'image/png';
        }

        if ($extension === 'webp') {
            return 'image/webp';
        }

        return 'image/jpeg';
    }
}
