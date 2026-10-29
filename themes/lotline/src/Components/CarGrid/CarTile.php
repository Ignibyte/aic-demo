<?php

declare(strict_types=1);

namespace App\Theme\Components\CarGrid;

/**
 * One car in a `CarGrid`: its card's link, title, mileage and price, and its main photo when it has one.
 */
final readonly class CarTile
{
    /**
     * @param string $id       The card's link's id.
     * @param string $href     The car's page.
     * @param string $title    What it is: its year, make and model.
     * @param string $mileage  Its mileage, as written (`34,500 miles`).
     * @param string $price    Its price, as written (`$28,995`).
     * @param string $photoSrc Where its main photo is served; empty when it has none.
     * @param string $photoAlt What the photo shows.
     */
    public function __construct(
        public string $id,
        public string $href,
        public string $title,
        public string $mileage,
        public string $price,
        public string $photoSrc,
        public string $photoAlt,
    ) {
    }
}
