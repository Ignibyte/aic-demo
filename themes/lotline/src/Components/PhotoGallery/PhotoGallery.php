<?php

declare(strict_types=1);

namespace App\Theme\Components\PhotoGallery;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A record's photos, from photo-gallery.phtml: a heading, the photos in a grid, the first larger as the main photo,
 * a line when there are none, and the controls that change them for staff who may. The grid is the strip (#31): it
 * carries `data-permanent` and an id named by the record and its photos, so Tessera keeps it, with its scroll, across a
 * redraw that brings the same photos, and gives another record, or other photos, a new one.
 */
final readonly class PhotoGallery implements Component
{
    /**
     * @param string               $id       Its id, and the stem of its heading's.
     * @param string               $title    Its heading.
     * @param string               $empty    What it says when there are no photos.
     * @param list<GalleryPhoto>   $photos   The photos, the main one first.
     * @param list<KeyedComponent> $controls What changes them, such as an upload form; empty for none.
     * @param string               $strip    The strip's id, from the record and its photos; empty for a strip Tessera need not keep.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $empty,
        public array $photos,
        public array $controls,
        public string $strip = '',
    ) {
    }
}
