<?php

declare(strict_types=1);

namespace App\Theme\Components\CompareTable;

/**
 * One car's column in a `CompareTable`: what each of its rows shows, already written.
 */
final readonly class ComparedCar
{
    /**
     * @param string $href     The car's page.
     * @param string $title    What it is: its year, make and model.
     * @param string $photoSrc Where its main photo is served; empty when it has none.
     * @param string $photoAlt What the photo shows.
     * @param string $year     Its model year.
     * @param string $make     Its make.
     * @param string $model    Its model.
     * @param string $trim     Its trim.
     * @param string $mileage  Its mileage, as written (`34,500 miles`).
     * @param string $price    Its price, as written (`$28,995`).
     * @param string $payment  Its monthly payment at the calculator's defaults, as written (`$312.45`).
     */
    public function __construct(
        public string $href,
        public string $title,
        public string $photoSrc,
        public string $photoAlt,
        public string $year,
        public string $make,
        public string $model,
        public string $trim,
        public string $mileage,
        public string $price,
        public string $payment,
    ) {
    }
}
