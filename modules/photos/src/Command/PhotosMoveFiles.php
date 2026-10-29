<?php

declare(strict_types=1);

namespace App\Photos\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Entity\Application\EntityStore;
use AIC\Storage\Application\PublicFiles;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Repository\VehiclePhotoRepository;
use InvalidArgumentException;
use Override;
use RuntimeException;

use function count;
use function file_get_contents;
use function hash;
use function is_dir;
use function is_file;
use function rmdir;
use function scandir;
use function unlink;

/**
 * `bin/aic photos:move-files`: moves the photo files kept before AIC's public files (#23), at the project's
 * `var/photos/{vehicle id}/{photo id}`, into the public files under each row's key, then removes each folder it
 * emptied, `var/photos/` last. A row from before the hash was kept at insert gets it from its file. Run again, it finds nothing to move.
 * The old folder is found from this file, as the removed `PhotoFiles` found it. Exit 2 with its usage for any argument.
 */
final readonly class PhotosMoveFiles implements Command
{
    /**
     * The photos' rows.
     */
    private VehiclePhotoRepository $vehiclePhotoRepository;

    /**
     * @param EntityStore $entityStore The entity store.
     * @param PublicFiles $publicFiles AIC's public files, where the photos go.
     */
    public function __construct(
        EntityStore $entityStore,
        private PublicFiles $publicFiles,
    ) {
        $this->vehiclePhotoRepository = new VehiclePhotoRepository($entityStore);
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @throws InvalidArgumentException When the entity type is malformed, a key is not one, or the exit code is invalid.
     * @throws RuntimeException         When the photos cannot be read or a hash recorded (`QueryException`), or an old
     *                                  file cannot be read or its new one written.
     *
     * @return CommandResponse How many files it moved.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (count($commandInput->arguments()) !== 0) {
            return new CommandResult(['usage: bin/aic photos:move-files'], 2);
        }

        $moved = 0;

        foreach ($this->vehiclePhotoRepository->vehicleIds() as $vehicleId) {
            foreach ($this->vehiclePhotoRepository->photos($vehicleId) as $vehiclePhoto) {
                $moved += $this->move($vehicleId, $vehiclePhoto);
            }

            $this->removeFolder($this->folder($vehicleId));
        }

        $this->removeFolder($this->folder(0));

        return new CommandResult(['photos:move-files moved ' . $moved . ' photo(s).'], 0);
    }

    /**
     * @param int          $vehicleId    The photo's vehicle.
     * @param VehiclePhoto $vehiclePhoto The photo.
     *
     * @throws InvalidArgumentException When its key is not one.
     * @throws RuntimeException         When its hash cannot be recorded (`QueryException`), or its old file cannot be
     *                                  read or its new one written.
     *
     * @return int 1 when its old file was there and moved; 0 when there was none.
     */
    private function move(int $vehicleId, VehiclePhoto $vehiclePhoto): int
    {
        $old = $this->folder($vehicleId) . '/' . $vehiclePhoto->id;

        if (!is_file($old)) {
            return 0;
        }

        $contents = file_get_contents($old);

        if ($contents === false) {
            throw new RuntimeException('The photo file ' . $old . ' cannot be read.');
        }

        $sha256 = $vehiclePhoto->sha256;

        if ($sha256 === '') {
            $sha256 = hash('sha256', $contents);
            $this->vehiclePhotoRepository->hashed($vehicleId, $vehiclePhoto->id, $sha256);
        }

        $this->publicFiles->put((new VehiclePhoto($vehiclePhoto->id, 0, $vehiclePhoto->mediaType, $sha256))->key(), $contents);
        unlink($old);

        return 1;
    }

    /**
     * @param string $folder An old folder: it goes once it holds nothing but `.` and `..`.
     */
    private function removeFolder(string $folder): void
    {
        $names = is_dir($folder)
            ? scandir($folder)
            : false;

        if ($names === false || count($names) > 2) {
            return;
        }

        rmdir($folder);
    }

    /**
     * @param int $vehicleId A vehicle's id; 0 for the folder that held every vehicle's.
     *
     * @return string Its old photo folder, under the project's `var/photos/`.
     */
    private function folder(int $vehicleId): string
    {
        $folder = __DIR__ . '/../../../../var/photos';

        return $vehicleId === 0
            ? $folder
            : $folder . '/' . $vehicleId;
    }
}
