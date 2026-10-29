<?php

declare(strict_types=1);

namespace App\Photos\Entity;

use function substr;

/**
 * One photo of a vehicle: its row's id, its place among the vehicle's photos in the order they were added, from 1,
 * its media type and the SHA-256 of its bytes. A photo is addressed by its vehicle's page and its position, so
 * gate:23's two passes, whose rows get different ids, request the same paths.
 */
final readonly class VehiclePhoto
{
    /**
     * @param int    $id        Its row's id.
     * @param int    $position  Its place among the vehicle's photos, from 1.
     * @param string $mediaType Its media type, such as `image/jpeg`.
     * @param string $sha256    The SHA-256 of its bytes, in hex.
     */
    public function __construct(
        public int $id,
        public int $position,
        public string $mediaType,
        public string $sha256,
    ) {
    }

    /**
     * @param string $vehiclePath The vehicle's page, `/app/inventory/{VIN}`.
     *
     * @return string Where it is served, `{vehicle page}/photos/{position}`.
     */
    public function path(string $vehiclePath): string
    {
        return $vehiclePath . '/photos/' . $this->position;
    }

    /**
     * A browser keeps the images a document has shown by their URL, whatever their cache headers say, so when a delete
     * moves another photo to this position, the same path would show the deleted photo again. The source names the
     * bytes too; it is the same on both of gate:23's passes, which upload the same files.
     *
     * @param string $vehiclePath The vehicle's page.
     *
     * @return string The image's source: its path, and the start of its hash as `?v=`.
     */
    public function source(string $vehiclePath): string
    {
        return $this->path($vehiclePath) . '?v=' . substr($this->sha256, 0, 16);
    }

    /**
     * @param string $vehiclePath The vehicle's page.
     *
     * @return string What its delete form seals: its path and its id, so a form for a photo that has since moved to
     *                another position is refused.
     */
    public function sealed(string $vehiclePath): string
    {
        return $this->path($vehiclePath) . '#' . $this->id;
    }
}
