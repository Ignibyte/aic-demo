<?php

declare(strict_types=1);

namespace App\Photos\Service;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

use function hash_file;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function scandir;
use function unlink;

/**
 * Where the photos' files are kept: the project's `var/photos/{vehicle id}/{photo id}`, outside `public/`, each named
 * by the server from its row, never by what the client sent. The directory is found from this file, as
 * `StaffSettings` finds `config/`.
 */
final readonly class PhotoFiles
{
    /**
     * @param int $vehicleId A vehicle's id.
     * @param int $photoId   One of its photos' id.
     *
     * @return string The photo's file.
     */
    public function path(int $vehicleId, int $photoId): string
    {
        return $this->directory($vehicleId) . '/' . $photoId;
    }

    /**
     * @param UploadedFileInterface $uploadedFile A checked upload.
     * @param int                   $vehicleId    The vehicle it shows.
     * @param int                   $photoId      Its row's id.
     *
     * @throws RuntimeException When the vehicle's directory cannot be made or the upload cannot be moved into it, its
     *                          path refused included, so the caller's rollback catches every failure.
     */
    public function keep(UploadedFileInterface $uploadedFile, int $vehicleId, int $photoId): void
    {
        $directory = $this->directory($vehicleId);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException('The photo directory ' . $directory . ' cannot be made.');
        }

        try {
            $uploadedFile->moveTo($this->path($vehicleId, $photoId));
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new RuntimeException('The photo cannot be moved to ' . $directory . '.', 0, $invalidArgumentException);
        }
    }

    /**
     * @param int $vehicleId A vehicle's id.
     * @param int $photoId   One of its photos' id.
     *
     * @throws RuntimeException When the file cannot be read.
     *
     * @return string The SHA-256 of the photo's file, in hex.
     */
    public function sha256(int $vehicleId, int $photoId): string
    {
        $sha256 = hash_file('sha256', $this->path($vehicleId, $photoId));

        if ($sha256 === false) {
            throw new RuntimeException('The photo file of photo ' . $photoId . ' cannot be read.');
        }

        return $sha256;
    }

    /**
     * @param int $vehicleId A vehicle's id.
     * @param int $photoId   One of its photos' id.
     */
    public function remove(int $vehicleId, int $photoId): void
    {
        self::unlinkFile($this->path($vehicleId, $photoId));
    }

    /**
     * Removes every file a deleted vehicle left, then its directory.
     *
     * @param int $vehicleId A vehicle's id.
     */
    public function forget(int $vehicleId): void
    {
        $directory = $this->directory($vehicleId);
        $names = is_dir($directory)
            ? scandir($directory)
            : false;

        if ($names === false) {
            return;
        }

        foreach ($names as $name) {
            self::unlinkFile($directory . '/' . $name);
        }

        rmdir($directory);
    }

    /**
     * @param int $vehicleId A vehicle's id.
     *
     * @return string The directory of its photos, under the project's `var/photos/`.
     */
    private function directory(int $vehicleId): string
    {
        return __DIR__ . '/../../../../var/photos/' . $vehicleId;
    }

    /**
     * @param string $path A path: a file is removed, and anything else, such as `.` or a file gone already, is left.
     */
    private static function unlinkFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        unlink($path);
    }
}
