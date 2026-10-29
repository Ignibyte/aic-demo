<?php

declare(strict_types=1);

namespace App\Photos\Service;

use AIC\Controller\Domain\FileDownload;
use AIC\Entity\Application\EntityStore;
use AIC\Form\Application\UploadCheck;
use AIC\Form\Domain\UploadKind;
use AIC\Form\Exception\RefusedUpload;
use AIC\Routing\Domain\RouteNotFoundException;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Repository\VehiclePhotoRepository;
use App\Staff\Entity\Role;
use App\Staff\Service\StaffSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * A vehicle's photos, which inventory's pages show and change by the vehicle's id: read, added from a posted file,
 * removed, forgotten with a deleted vehicle, and served. Who may change them is a sales manager or an admin.
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
     * @param PhotoFiles   $photoFiles   The photos' files.
     * @param StaffSession $staffSession Who is signed in.
     */
    public function __construct(
        EntityStore $entityStore,
        private PhotoFiles $photoFiles = new PhotoFiles(),
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
     * @throws RuntimeException When the photos cannot be counted, or the row written, or the file read or moved.
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
            $label = $this->uploadCheck()->check($uploadedFile);
        } catch (RefusedUpload $refusedUpload) {
            return $refusedUpload->getMessage();
        }

        return $this->keep($uploadedFile, $vehicleId, $this->mediaType($label))
            ? ''
            : self::FULL;
    }

    /**
     * @param int          $vehicleId    A vehicle's id.
     * @param VehiclePhoto $vehiclePhoto One of its photos.
     *
     * @throws LogicException   When its entity type is malformed.
     * @throws RuntimeException When the row cannot be deleted.
     *
     * @return bool Whether it was deleted, its file with it; false when it was gone already.
     */
    public function remove(int $vehicleId, VehiclePhoto $vehiclePhoto): bool
    {
        if (!$this->vehiclePhotoRepository->delete($vehicleId, $vehiclePhoto->id)) {
            return false;
        }

        $this->photoFiles->remove($vehicleId, $vehiclePhoto->id);

        return true;
    }

    /**
     * Removes a deleted vehicle's files; its rows went with it, by the foreign key's cascade.
     *
     * @param int $vehicleId The deleted vehicle's id.
     */
    public function forget(int $vehicleId): void
    {
        $this->photoFiles->forget($vehicleId);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request for the photo: its range and conditions.
     * @param int                    $vehicleId     The vehicle's id; 0 for none.
     * @param int                    $position      The photo's position, from 1.
     *
     * @throws LogicException   When their entity type is malformed.
     * @throws RuntimeException When the photos cannot be read, or the vehicle has no photo there
     *                          (`RouteNotFoundException`, which AIC answers 404).
     *
     * @return FileDownload The photo, inline, with its media type; AIC sends its ETag and honours the request's
     *                      conditions and range.
     */
    public function download(ServerRequestInterface $serverRequest, int $vehicleId, int $position): FileDownload
    {
        $vehiclePhoto = $vehicleId > 0
            ? $this->photo($vehicleId, $position)
            : null;

        if (!$vehiclePhoto instanceof VehiclePhoto) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        return new FileDownload($this->photoFiles->path($vehicleId, $vehiclePhoto->id), $vehiclePhoto->mediaType, '', $serverRequest);
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
     * The row first, so the file is named by its id. The vehicle's photos are counted again after it: two uploads sent
     * together past the count before it may both have written, and a row that takes the vehicle over the limit is
     * deleted, so at most `MOST` stay whatever the timing. Then the file is moved and its hash read from it, rather
     * than from an 8 MB string; when either fails, the file and the row both go.
     *
     * @param UploadedFileInterface $uploadedFile A checked upload.
     * @param int                   $vehicleId    The vehicle it is for.
     * @param string                $mediaType    Its media type.
     *
     * @throws LogicException   When a value does not fit its field.
     * @throws RuntimeException When a row cannot be written or counted, or the file moved or read.
     *
     * @return bool Whether it was kept; false when the vehicle was full by the time its row was written.
     */
    private function keep(UploadedFileInterface $uploadedFile, int $vehicleId, string $mediaType): bool
    {
        $photoId = $this->vehiclePhotoRepository->add($vehicleId, $mediaType, $uploadedFile->getSize() ?? 0);

        if ($this->vehiclePhotoRepository->count($vehicleId) > self::MOST) {
            $this->vehiclePhotoRepository->delete($vehicleId, $photoId);

            return false;
        }

        try {
            $this->photoFiles->keep($uploadedFile, $vehicleId, $photoId);
            $this->vehiclePhotoRepository->hashed($vehicleId, $photoId, $this->photoFiles->sha256($vehicleId, $photoId));
        } catch (RuntimeException $runtimeException) {
            $this->photoFiles->remove($vehicleId, $photoId);
            $this->vehiclePhotoRepository->delete($vehicleId, $photoId);

            throw $runtimeException;
        }

        return true;
    }

    /**
     * @return UploadCheck AIC's check of a photo's content and size.
     */
    private function uploadCheck(): UploadCheck
    {
        return new UploadCheck(self::BYTES, [UploadKind::Jpeg, UploadKind::Png, UploadKind::WebP]);
    }

    /**
     * @param string $label The kind `UploadCheck` accepted, for a person (`PNG image`).
     *
     * @return string Its media type.
     */
    private function mediaType(string $label): string
    {
        if ($label === UploadKind::Png->label()) {
            return 'image/png';
        }

        if ($label === UploadKind::WebP->label()) {
            return 'image/webp';
        }

        return 'image/jpeg';
    }
}
