<?php

declare(strict_types=1);

namespace App\Theme\Components\PhotoGallery;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A record's photos, from photo-gallery.phtml: a heading, the photos in a grid, the first larger as the main photo,
 * a line when there are none, and the controls that change them for staff who may.
 */
final readonly class PhotoGallery implements Component
{
    /**
     * @param string               $id       Its id, and the stem of its heading's.
     * @param string               $title    Its heading.
     * @param string               $empty    What it says when there are no photos.
     * @param list<GalleryPhoto>   $photos   The photos, the main one first.
     * @param list<KeyedComponent> $controls What changes them, such as an upload form; empty for none.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $empty,
        public array $photos,
        public array $controls,
    ) {
    }
}
