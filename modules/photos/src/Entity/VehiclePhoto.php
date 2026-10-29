<?php

declare(strict_types=1);

namespace App\Photos\Entity;

use AIC\Storage\Application\PublicFiles;

/**
 * One photo of a vehicle: its row's id, its place among the vehicle's photos in the order they were added, from 1,
 * its media type and the SHA-256 of its bytes. Its file is kept in AIC's public files under a key named by its bytes
 * (#23); its staff and storefront Streams address it by its vehicle's page and its position, so gate:23's two passes,
 * whose rows get different ids, request the same paths.
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
     * @return string Its key in AIC's public files, `photos/{sha256}.{extension}`: named by its bytes, so gate:23's two
     *                passes, which upload the same files, name the same keys, and a browser that kept an image by its
     *                URL never shows another photo's bytes there.
     */
    public function key(): string
    {
        return 'photos/' . $this->sha256 . '.' . $this->extension();
    }

    /**
     * @return string The image's source, where AIC serves its key: `/files/photos/{sha256}.{extension}`.
     */
    public function source(): string
    {
        return PublicFiles::PREFIX . $this->key();
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

    /**
     * @return string The extension its media type is stored under: `png`, `webp`, or `jpg` for a JPEG.
     */
    private function extension(): string
    {
        if ($this->mediaType === 'image/png') {
            return 'png';
        }

        if ($this->mediaType === 'image/webp') {
            return 'webp';
        }

        return 'jpg';
    }
}
