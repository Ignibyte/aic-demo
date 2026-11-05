<?php

declare(strict_types=1);

namespace App\Theme\Components\CarBrochure;

use AIC\Component\Contracts\Component;
use App\Theme\Components\DetailCard\Fact;
use App\Theme\Components\PhotoGallery\GalleryPhoto;

/**
 * A car's one-page brochure (#44), as a job keeps it to be printed: the dealership's name over the car's title, trim
 * and price, its photos as plain images, the main one first and larger, its details, and the dealership's address,
 * phone and hours. A line for the screen says how to print it; the stylesheet's print rules hide it.
 */
final readonly class CarBrochure implements Component
{
    /**
     * @param string             $id         Its heading's id, and the stem of its parts'.
     * @param string             $dealership The dealership's name.
     * @param string             $title      What the car is: its year, make and model.
     * @param string             $subtitle   A line under the title, such as its trim.
     * @param string             $price      Its price, as written (`$28,995`).
     * @param list<Fact>         $facts      Its details.
     * @param list<GalleryPhoto> $photos     Its photos, the main one first; empty for none.
     * @param string             $contact    The dealership's address, phone and hours.
     */
    public function __construct(
        public string $id,
        public string $dealership,
        public string $title,
        public string $subtitle,
        public string $price,
        public array $facts,
        public array $photos,
        public string $contact,
    ) {
    }
}
