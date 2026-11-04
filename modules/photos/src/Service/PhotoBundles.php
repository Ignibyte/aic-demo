<?php

declare(strict_types=1);

namespace App\Photos\Service;

use AIC\Controller\Domain\FileDownload;
use AIC\Entity\Application\EntityStore;
use AIC\Queue\Application\PostgresQueue;
use AIC\Routing\Domain\RouteNotFoundException;
use AIC\Storage\Application\PrivateFiles;
use AIC\Storage\Application\PublicFiles;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Entity\ZipEntry;
use App\Photos\Repository\VehiclePhotoRepository;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function count;
use function file_get_contents;
use function strrpos;
use function substr;

/**
 * A vehicle's photos as one ZIP for staff (#36), its bundle: kept in AIC's private files at
 * `bundles/{vehicle id}.zip`, written by the `photo-bundle` job whenever its photos change, and served through
 * AIC's `FileDownload`, which answers its ETag, conditions and byte ranges. Each entry is a photo's stored file, named
 * `photo-{position}.{extension}`, in position order.
 *
 * @api
 */
final readonly class PhotoBundles
{
    /**
     * The job that writes a bundle.
     */
    public const string JOB = 'photo-bundle';

    /**
     * The bundle's media type.
     */
    private const string ZIP = 'application/zip';

    /**
     * The photos' rows.
     */
    private VehiclePhotoRepository $vehiclePhotoRepository;

    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param PublicFiles   $publicFiles   AIC's public files, where the photos' files are kept.
     * @param PrivateFiles  $privateFiles  AIC's private files, where the bundles are kept.
     * @param PostgresQueue $postgresQueue Queues the job that writes a bundle.
     * @param StoredZip     $storedZip     Writes the archive.
     */
    public function __construct(
        EntityStore $entityStore,
        private PublicFiles $publicFiles,
        private PrivateFiles $privateFiles,
        private PostgresQueue $postgresQueue,
        private StoredZip $storedZip = new StoredZip(),
    ) {
        $this->vehiclePhotoRepository = new VehiclePhotoRepository($entityStore);
    }

    /**
     * @param int $vehicleId The id of a vehicle whose photos changed.
     *
     * @throws RuntimeException When the job cannot be stored (`QueryException`).
     * @throws LogicException   When its payload cannot be encoded, which a vehicle's id always can.
     */
    public function queue(int $vehicleId): void
    {
        try {
            $this->postgresQueue->dispatch(self::JOB, ['vehicle' => (string) $vehicleId], 0);
        } catch (JsonException $jsonException) {
            throw new LogicException('The photo-bundle job of vehicle ' . $vehicleId . ' cannot be encoded.', 0, $jsonException);
        }
    }

    /**
     * Writes the vehicle's bundle from its photos as they are now, over the one before; with no photos, deletes it.
     *
     * @param int $vehicleId A vehicle's id.
     *
     * @throws LogicException   When the photos' entity type is malformed, or a key is not a storage key
     *                          (`InvalidArgumentException`).
     * @throws RuntimeException When the photos cannot be read, a photo's file is gone or unreadable, or the bundle
     *                          cannot be written.
     *
     * @return int How many photos the bundle holds; 0 when there is none.
     */
    public function write(int $vehicleId): int
    {
        $entries = [];

        foreach ($this->vehiclePhotoRepository->photos($vehicleId) as $vehiclePhoto) {
            $zipEntry = $this->entry($vehiclePhoto);
            $entries[] = $zipEntry;
        }

        if ($entries === []) {
            $this->privateFiles->delete($this->key($vehicleId));

            return 0;
        }

        $this->privateFiles->put($this->key($vehicleId), $this->storedZip->bytes($entries));

        return count($entries);
    }

    /**
     * @param int $vehicleId A vehicle's id; 0 for none.
     *
     * @throws InvalidArgumentException When the key is not a storage key.
     *
     * @return bool Whether its bundle is written.
     */
    public function exists(int $vehicleId): bool
    {
        return $vehicleId > 0 && $this->privateFiles->exists($this->key($vehicleId));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request for the bundle: its range and conditions.
     * @param int                    $vehicleId     The vehicle's id.
     * @param string                 $vin           Its VIN as stored, which names the download.
     *
     * @throws RuntimeException         When the bundle is not written (`RouteNotFoundException`, which AIC answers 404).
     * @throws InvalidArgumentException When the key is not a storage key.
     *
     * @return FileDownload The bundle, saved as `{VIN}-photos.zip`; AIC sends its ETag and honours the request's
     *                      conditions, `If-Range` and range.
     */
    public function download(ServerRequestInterface $serverRequest, int $vehicleId, string $vin): FileDownload
    {
        if (!$this->exists($vehicleId)) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        return $this->privateFiles->download($this->key($vehicleId), self::ZIP, $vin . '-photos.zip', $serverRequest);
    }

    /**
     * @param VehiclePhoto $vehiclePhoto One of the vehicle's photos.
     *
     * @throws RuntimeException When its file is gone or cannot be read.
     *
     * @return ZipEntry Its file as `photo-{position}.{extension}`.
     */
    private function entry(VehiclePhoto $vehiclePhoto): ZipEntry
    {
        $key = $vehiclePhoto->key();
        $path = $this->publicFiles->served($key);
        $contents = $path === null
            ? false
            : file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('The file of photo ' . $vehiclePhoto->id . ', ' . $key . ', cannot be read.');
        }

        return new ZipEntry('photo-' . $vehiclePhoto->position . substr($key, (int) strrpos($key, '.')), $contents);
    }

    /**
     * @param int $vehicleId A vehicle's id.
     *
     * @return string Its bundle's key in the private files.
     */
    private function key(int $vehicleId): string
    {
        return 'bundles/' . $vehicleId . '.zip';
    }
}
