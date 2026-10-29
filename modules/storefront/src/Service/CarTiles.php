<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Inventory\Entity\ListedVehicle;
use App\Photos\Service\VehiclePhotos;
use App\Storefront\Form\CarQuery;
use App\Theme\Components\CarGrid\CarGrid;
use App\Theme\Components\CarGrid\CarTile;
use App\Theme\Components\TableResults\TableResults;
use LogicException;
use RuntimeException;

/**
 * The public list's results: a card per car on the page, each with its main photo when it has one, and past one page
 * `CarLinks`' link per page. `CarResults` builds them from what it read when they are rendered.
 */
final readonly class CarTiles
{
    /**
     * @param VehiclePhotos $vehiclePhotos The cars' photos.
     * @param CarSlugs      $carSlugs      Each car's page.
     * @param CarLinks      $carLinks      The page links.
     * @param CarFigures    $carFigures    Each car's price and mileage as written.
     */
    public function __construct(
        private VehiclePhotos $vehiclePhotos,
        private CarSlugs $carSlugs = new CarSlugs(),
        private CarLinks $carLinks = new CarLinks(),
        private CarFigures $carFigures = new CarFigures(),
    ) {
    }

    /**
     * @param CarQuery            $carQuery       What the list was asked for.
     * @param list<ListedVehicle> $listedVehicles The page's cars.
     * @param int                 $count          How many cars match.
     * @param int                 $page           The page shown.
     * @param int                 $pages          How many pages there are.
     *
     * @throws LogicException   When an entity type is malformed, or a key is.
     * @throws RuntimeException When the vehicles or their photos cannot be read.
     *
     * @return TableResults The grid and, past one page, the pages.
     */
    public function results(CarQuery $carQuery, array $listedVehicles, int $count, int $page, int $pages): TableResults
    {
        $tiles = [];

        foreach ($listedVehicles as $listedVehicle) {
            $tiles[] = $this->tile($listedVehicle);
        }

        $empty = 'No car for sale matches. Try fewer filters, or clear them.';
        $parts = [new KeyedComponent('grid', new CarGrid('cars', $this->summary($count, $page, $pages), $empty, $tiles))];

        if ($pages > 1) {
            $parts[] = new KeyedComponent('pages', $this->carLinks->pagination($carQuery, $page, $pages));
        }

        return new TableResults($parts);
    }

    /**
     * @param ListedVehicle $listedVehicle A car for sale.
     *
     * @throws LogicException   When the photos' entity type is malformed.
     * @throws RuntimeException When its photos cannot be read.
     *
     * @return CarTile Its card: its title, mileage and price, linked to its page, with its first photo when it has one.
     */
    private function tile(ListedVehicle $listedVehicle): CarTile
    {
        $path = $this->carSlugs->path($listedVehicle);
        $title = $listedVehicle->title();
        $photos = $this->vehiclePhotos->photos($listedVehicle->id);
        $source = $photos === []
            ? ''
            : $photos[0]->source($path);

        return new CarTile(
            'car-' . $this->carSlugs->slug($listedVehicle),
            $path,
            $title,
            $this->carFigures->mileage($listedVehicle->mileage),
            $this->carFigures->price($listedVehicle->price),
            $source,
            $title . ', main photo',
        );
    }

    /**
     * @param int $count How many cars match.
     * @param int $page  The page shown.
     * @param int $pages How many pages there are.
     *
     * @return string How many cars match, and past one page which page this is.
     */
    private function summary(int $count, int $page, int $pages): string
    {
        $cars = $count === 1
            ? '1 car for sale'
            : $count . ' cars for sale';

        return $pages > 1
            ? $cars . ', page ' . $page . ' of ' . $pages
            : $cars;
    }
}
