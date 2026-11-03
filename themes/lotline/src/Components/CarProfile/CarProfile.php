<?php

declare(strict_types=1);

namespace App\Theme\Components\CarProfile;

use AIC\Component\Contracts\Component;
use App\Theme\Components\DetailCard\Fact;
use App\Theme\Components\FinanceCalculator\FinanceCalculator;
use App\Theme\Components\PhotoGallery\PhotoGallery;

/**
 * One car for sale, from car-profile.phtml (#17): its photos in the theme's `PhotoGallery`, with no controls; then its
 * title and price, its payment calculator under the price (#26), its facts, and a link back to every car.
 */
final readonly class CarProfile implements Component
{
    /**
     * @param string            $id       Its heading's id, and the stem of its price's.
     * @param string            $title    What it is: its year, make and model.
     * @param string            $subtitle A line under the title, such as its trim.
     * @param string            $price    Its price, as written (`$28,995`).
     * @param list<Fact>        $facts    Its details.
     * @param PhotoGallery      $gallery  Its photos, the main one first.
     * @param FinanceCalculator $finance  Its payment calculator, under its price.
     * @param string            $backHref The cars for sale.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $subtitle,
        public string $price,
        public array $facts,
        public PhotoGallery $gallery,
        public FinanceCalculator $finance,
        public string $backHref,
    ) {
    }
}
