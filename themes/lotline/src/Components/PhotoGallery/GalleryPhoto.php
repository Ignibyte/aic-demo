<?php

declare(strict_types=1);

namespace App\Theme\Components\PhotoGallery;

use AIC\Component\Domain\KeyedComponent;

/**
 * One photo in a `PhotoGallery`: the image's path and text alternative, its id, whether it is the main photo, and
 * what acts on it, such as a delete form.
 */
final readonly class GalleryPhoto
{
    /**
     * @param string               $id      The image's id.
     * @param string               $src     Where the image is served.
     * @param string               $alt     What it shows, for a reader who cannot see it.
     * @param bool                 $main    Whether it is the main photo, shown larger and captioned.
     * @param list<KeyedComponent> $actions What acts on it, each keyed apart from every other photo's; empty for none.
     */
    public function __construct(
        public string $id,
        public string $src,
        public string $alt,
        public bool $main,
        public array $actions,
    ) {
    }
}
