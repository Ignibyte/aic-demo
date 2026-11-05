<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use App\Finance\Service\FinanceCalculators;
use App\Inventory\Entity\ListedVehicle;
use App\Photos\Service\VehiclePhotos;
use App\Settings\Entity\DealershipProfile;
use App\Theme\Components\CarBrochure\CarBrochure;
use App\Theme\Components\CarProfile\CarProfile;
use App\Theme\Components\DetailCard\Fact;
use App\Theme\Components\PhotoGallery\GalleryPhoto;
use App\Theme\Components\PhotoGallery\PhotoGallery;
use LogicException;
use RuntimeException;

use function array_slice;
use function count;

/**
 * One car's public page: its photos in the order they were added, in the theme's `PhotoGallery` with no controls, the
 * first the main one, each image's `alt` naming the car and the photo's number; its title, trim and price; finance's
 * payment calculator at its price (#26); and its details, as the theme's `Fact`s. Its brochure (#44) is built from the
 * same facts and photos, so the two say the same. Nothing here knows its VIN or its cost.
 */
final readonly class CarProfiles
{
    /**
     * The most photos a brochure shows, so a car with 12 still prints on one page.
     */
    private const int BROCHURE_PHOTOS = 5;

    /**
     * @param VehiclePhotos      $vehiclePhotos      The car's photos.
     * @param CarFigures         $carFigures         Its price and mileage as written.
     * @param FinanceCalculators $financeCalculators Builds its payment calculator.
     * @param CarSlugs           $carSlugs           Its slug, for its Compare tick (#33).
     */
    public function __construct(
        private VehiclePhotos $vehiclePhotos,
        private CarFigures $carFigures = new CarFigures(),
        private FinanceCalculators $financeCalculators = new FinanceCalculators(),
        private CarSlugs $carSlugs = new CarSlugs(),
    ) {
    }

    /**
     * @param ListedVehicle $listedVehicle  A car for sale.
     * @param int           $aprBasisPoints The dealership's default APR, in basis points, for its calculator.
     *
     * @throws LogicException   When the photos' entity type is malformed.
     * @throws RuntimeException When its photos cannot be read.
     *
     * @return CarProfile Its page's content.
     */
    public function profile(ListedVehicle $listedVehicle, int $aprBasisPoints): CarProfile
    {
        $photoGallery = new PhotoGallery('car-photos', 'Photos', 'No photos yet.', $this->photos($listedVehicle), []);

        return new CarProfile(
            'car',
            $listedVehicle->title(),
            $listedVehicle->trim,
            $this->carFigures->price($listedVehicle->price),
            $this->facts($listedVehicle),
            $photoGallery,
            $this->financeCalculators->calculator($listedVehicle->price, $aprBasisPoints),
            CarSlugs::PAGES,
            $this->carSlugs->slug($listedVehicle),
        );
    }

    /**
     * @param ListedVehicle     $listedVehicle     A car for sale.
     * @param DealershipProfile $dealershipProfile The dealership's profile: its name, address, phone and hours.
     *
     * @throws LogicException   When the photos' entity type is malformed.
     * @throws RuntimeException When its photos cannot be read.
     *
     * @return CarBrochure Its one-page brochure: the car page's title, trim, price and facts, its first 5 photos, and the
     *                     dealership's name and contact line.
     */
    public function brochure(ListedVehicle $listedVehicle, DealershipProfile $dealershipProfile): CarBrochure
    {
        return new CarBrochure(
            'brochure',
            $dealershipProfile->name,
            $listedVehicle->title(),
            $listedVehicle->trim,
            $this->carFigures->price($listedVehicle->price),
            $this->facts($listedVehicle),
            array_slice($this->photos($listedVehicle), 0, self::BROCHURE_PHOTOS),
            $dealershipProfile->facts(),
        );
    }

    /**
     * @param ListedVehicle $listedVehicle A car for sale.
     *
     * @return list<Fact> Its year, make, model, trim, body style, mileage and colour.
     */
    private function facts(ListedVehicle $listedVehicle): array
    {
        return [
            new Fact('Year', (string) $listedVehicle->year, false),
            new Fact('Make', $listedVehicle->make, false),
            new Fact('Model', $listedVehicle->model, false),
            new Fact('Trim', $listedVehicle->trim, false),
            new Fact('Body style', $listedVehicle->bodyStyle, false),
            new Fact('Mileage', $this->carFigures->mileage($listedVehicle->mileage), false),
            new Fact('Colour', $listedVehicle->exteriorColour, false),
        ];
    }

    /**
     * @param ListedVehicle $listedVehicle A car for sale.
     *
     * @throws LogicException   When the photos' entity type is malformed.
     * @throws RuntimeException When its photos cannot be read.
     *
     * @return list<GalleryPhoto> Its photos, the first the main one, each from AIC's public files.
     */
    private function photos(ListedVehicle $listedVehicle): array
    {
        $photos = $this->vehiclePhotos->photos($listedVehicle->id);
        $total = count($photos);
        $galleryPhotos = [];

        foreach ($photos as $photo) {
            $number = $photo->position;
            $alt = $listedVehicle->title() . ', photo ' . $number . ' of ' . $total;
            $galleryPhotos[] = new GalleryPhoto('car-photo-' . $number, $photo->source(), $alt, $number === 1, []);
        }

        return $galleryPhotos;
    }
}
