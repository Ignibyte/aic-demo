<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use App\Inventory\Entity\ListedVehicle;
use App\Photos\Service\VehiclePhotos;
use App\Theme\Components\CarProfile\CarProfile;
use App\Theme\Components\DetailCard\Fact;
use App\Theme\Components\PhotoGallery\GalleryPhoto;
use App\Theme\Components\PhotoGallery\PhotoGallery;
use LogicException;
use RuntimeException;

use function count;

/**
 * One car's public page: its photos in the order they were added, in the theme's `PhotoGallery` with no controls, the
 * first the main one, each image's `alt` naming the car and the photo's number; its title, trim and price; and its
 * details, as the theme's `Fact`s. Nothing here knows its VIN or its cost.
 */
final readonly class CarProfiles
{
    /**
     * @param VehiclePhotos $vehiclePhotos The car's photos.
     * @param CarFigures    $carFigures    Its price and mileage as written.
     */
    public function __construct(
        private VehiclePhotos $vehiclePhotos,
        private CarFigures $carFigures = new CarFigures(),
    ) {
    }

    /**
     * @param ListedVehicle $listedVehicle A car for sale.
     *
     * @throws LogicException   When the photos' entity type is malformed.
     * @throws RuntimeException When its photos cannot be read.
     *
     * @return CarProfile Its page's content.
     */
    public function profile(ListedVehicle $listedVehicle): CarProfile
    {
        $title = $listedVehicle->title();
        $facts = [
            new Fact('Year', (string) $listedVehicle->year, false),
            new Fact('Make', $listedVehicle->make, false),
            new Fact('Model', $listedVehicle->model, false),
            new Fact('Trim', $listedVehicle->trim, false),
            new Fact('Body style', $listedVehicle->bodyStyle, false),
            new Fact('Mileage', $this->carFigures->mileage($listedVehicle->mileage), false),
            new Fact('Colour', $listedVehicle->exteriorColour, false),
        ];
        $price = $this->carFigures->price($listedVehicle->price);
        $photoGallery = new PhotoGallery('car-photos', 'Photos', 'No photos yet.', $this->photos($listedVehicle), []);

        return new CarProfile('car', $title, $listedVehicle->trim, $price, $facts, $photoGallery, CarSlugs::PAGES);
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
