<?php

declare(strict_types=1);

namespace App\Storefront\Service\Feed;

use AIC\Controller\Domain\XmlDocument;
use AIC\Controller\Domain\XmlElement;
use App\Inventory\Entity\Feed\FeedListing;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Photos\Entity\VehiclePhoto;
use App\Settings\Entity\SettingsConfig;
use App\Storefront\Service\CarFigures;
use App\Storefront\Service\CarFinder;
use App\Storefront\Service\CarSlugs;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * The XML feed for listing sites (#37), `/feeds/vehicles.xml`: a `vehicles` element holding a `vehicle` for each car
 * for sale, in the public list's first order, each with its VIN, what it is, its mileage, its list price as a decimal
 * string, its page's absolute URL and its photos' absolute URLs, the main one first. The cars come from inventory's
 * `FeedListing`s, which carry the VIN and no cost; the photos of every car are read at once (#25); each URL is the
 * configured site address before the path.
 */
final readonly class VehicleXmlFeed
{
    /**
     * @param ListedVehicleRepository $listedVehicleRepository The cars for sale, with their VINs.
     * @param CarFinder               $carFinder               Their photos.
     * @param SettingsConfig          $settingsConfig          The site's address.
     * @param CarSlugs                $carSlugs                Each car's page.
     * @param CarFigures              $carFigures              Writes the price as a decimal string, as the JSON feed does.
     */
    public function __construct(
        private ListedVehicleRepository $listedVehicleRepository,
        private CarFinder $carFinder,
        private SettingsConfig $settingsConfig,
        private CarSlugs $carSlugs = new CarSlugs(),
        private CarFigures $carFigures = new CarFigures(),
    ) {
    }

    /**
     * @throws LogicException   When an entity type is malformed, or a value holds a character XML 1.0 forbids.
     * @throws RuntimeException When the cars or their photos cannot be read.
     *
     * @return XmlDocument Every car for sale.
     */
    public function document(): XmlDocument
    {
        $feedListings = $this->listedVehicleRepository->feed();
        $listedVehicles = [];

        foreach ($feedListings as $feedListing) {
            $listedVehicles[] = $feedListing->vehicle;
        }

        $photos = $this->carFinder->photosOf($listedVehicles);
        $vehicles = [];

        foreach ($feedListings as $feedListing) {
            $vehicles[] = $this->vehicle($feedListing, $photos[$feedListing->vehicle->id] ?? []);
        }

        return new XmlDocument(new XmlElement('vehicles', children: $vehicles));
    }

    /**
     * @param FeedListing        $feedListing   A car for sale and its VIN.
     * @param list<VehiclePhoto> $vehiclePhotos Its photos, the first added first.
     *
     * @throws InvalidArgumentException When a value holds a character XML 1.0 forbids.
     *
     * @return XmlElement Its `vehicle` element.
     */
    private function vehicle(FeedListing $feedListing, array $vehiclePhotos): XmlElement
    {
        $listedVehicle = $feedListing->vehicle;
        $photos = [];

        foreach ($vehiclePhotos as $vehiclePhoto) {
            $photos[] = new XmlElement('photo', text: $this->settingsConfig->siteUrl . $vehiclePhoto->source());
        }

        return new XmlElement('vehicle', children: [
            new XmlElement('vin', text: $feedListing->vin),
            new XmlElement('year', text: (string) $listedVehicle->year),
            new XmlElement('make', text: $listedVehicle->make),
            new XmlElement('model', text: $listedVehicle->model),
            new XmlElement('trim', text: $listedVehicle->trim),
            new XmlElement('mileage', text: (string) $listedVehicle->mileage),
            new XmlElement('price', text: $this->carFigures->decimal($listedVehicle->price)),
            new XmlElement('url', text: $this->settingsConfig->siteUrl . $this->carSlugs->path($listedVehicle)),
            new XmlElement('photos', children: $photos),
        ]);
    }
}
