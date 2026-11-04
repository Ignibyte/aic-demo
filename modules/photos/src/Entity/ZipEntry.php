<?php

declare(strict_types=1);

namespace App\Photos\Entity;

/**
 * One file in a stored ZIP (#36): the name it is unpacked under and its bytes.
 */
final readonly class ZipEntry
{
    /**
     * @param string $name     Its name in the archive, plain ASCII, such as `photo-1.jpg`.
     * @param string $contents Its bytes.
     */
    public function __construct(
        public string $name,
        public string $contents,
    ) {
    }
}
