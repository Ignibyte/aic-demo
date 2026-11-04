<?php

declare(strict_types=1);

namespace App\Compare\Service;

use AIC\Database\Contracts\QueryException;
use App\Finance\Service\FinanceCalculators;
use App\Inventory\Entity\ListedVehicle;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Service\VehiclePhotos;
use App\Storefront\Service\CarFigures;
use App\Storefront\Service\CarSlugs;
use App\Theme\Components\CompareTable\ComparedCar;
use App\Theme\Components\CompareTable\CompareTable;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use UnexpectedValueException;

/**
 * The cars a comparison asks for, side by side (#33). The asked slugs that are well formed give their keys; inventory
 * reads the cars for sale with those keys in one statement (`ListedVehicleRepository::compared()`), and only a car
 * whose whole slug is one asked for is kept, in the order asked, so two cars that share a key never mix and a slug that
 * is malformed, sold, held or unknown drops out quietly. Their main photos are read in one statement too, and each
 * car's payment is the one its page's calculator starts at.
 */
final readonly class ComparisonTables
{
    /**
     * @param ListedVehicleRepository $listedVehicleRepository The cars for sale, by key.
     * @param VehiclePhotos           $vehiclePhotos           Their photos.
     * @param FinanceCalculators      $financeCalculators      Each car's payment at the calculator's defaults, and what
     *                                                         they assume.
     * @param CarSlugs                $carSlugs                Reads and writes slugs.
     * @param CarFigures              $carFigures              The price and mileage as written.
     */
    public function __construct(
        private ListedVehicleRepository $listedVehicleRepository,
        private VehiclePhotos $vehiclePhotos,
        private FinanceCalculators $financeCalculators = new FinanceCalculators(),
        private CarSlugs $carSlugs = new CarSlugs(),
        private CarFigures $carFigures = new CarFigures(),
    ) {
    }

    /**
     * @param list<string> $slugs          The asked slugs, in order and each once, at most three.
     * @param int          $aprBasisPoints The dealership's default APR, in basis points.
     *
     * @throws LogicException   When an entity type is malformed.
     * @throws RuntimeException When the cars or their photos cannot be read, or a stored status is no status.
     *
     * @return CompareTable|null The cars side by side, in the order asked; null when none of them is for sale.
     */
    public function table(array $slugs, int $aprBasisPoints): ?CompareTable
    {
        $listedVehicles = $this->cars($slugs);

        if ($listedVehicles === []) {
            return null;
        }

        $vehicleIds = [];

        foreach ($listedVehicles as $listedVehicle) {
            $vehicleIds[] = $listedVehicle->id;
        }

        $photos = $this->vehiclePhotos->photosOf($vehicleIds);
        $comparedCars = [];

        foreach ($listedVehicles as $listedVehicle) {
            $comparedCars[] = $this->column($listedVehicle, $photos[$listedVehicle->id] ?? [], $aprBasisPoints);
        }

        return new CompareTable('compare-table', $this->financeCalculators->assumption($aprBasisPoints), $comparedCars);
    }

    /**
     * @param list<string> $slugs The asked slugs, in order and each once.
     *
     * @throws QueryException           When the cars cannot be read.
     * @throws InvalidArgumentException When the entity type is malformed.
     * @throws UnexpectedValueException When a stored status is no status.
     *
     * @return list<ListedVehicle> The cars for sale whose whole slug is one asked for, in the order asked.
     */
    private function cars(array $slugs): array
    {
        $bySlug = [];

        foreach ($this->listedVehicleRepository->compared($this->keys($slugs)) as $listedVehicle) {
            $bySlug[$this->carSlugs->slug($listedVehicle)] = $listedVehicle;
        }

        $listedVehicles = [];

        foreach ($slugs as $slug) {
            $listedVehicle = $bySlug[$slug] ?? null;

            if (!$listedVehicle instanceof ListedVehicle) {
                continue;
            }

            $listedVehicles[] = $listedVehicle;
        }

        return $listedVehicles;
    }

    /**
     * @param list<string> $slugs The asked slugs.
     *
     * @return list<string> The keys of those that are well formed; two slugs may share one, which a read by key
     *                      answers once.
     */
    private function keys(array $slugs): array
    {
        $keys = [];

        foreach ($slugs as $slug) {
            if (!$this->carSlugs->holds($slug)) {
                continue;
            }

            $keys[] = $this->carSlugs->key($slug);
        }

        return $keys;
    }

    /**
     * @param ListedVehicle      $listedVehicle  A car for sale.
     * @param list<VehiclePhoto> $photos         Its photos, the first added first.
     * @param int                $aprBasisPoints The dealership's default APR, in basis points.
     *
     * @return ComparedCar Its column.
     */
    private function column(ListedVehicle $listedVehicle, array $photos, int $aprBasisPoints): ComparedCar
    {
        $title = $listedVehicle->title();
        $source = $photos === []
            ? ''
            : $photos[0]->source();

        return new ComparedCar(
            $this->carSlugs->path($listedVehicle),
            $title,
            $source,
            $title . ', main photo',
            (string) $listedVehicle->year,
            $listedVehicle->make,
            $listedVehicle->model,
            $listedVehicle->trim,
            $this->carFigures->mileage($listedVehicle->mileage),
            $this->carFigures->price($listedVehicle->price),
            $this->financeCalculators->monthly($listedVehicle->price, $aprBasisPoints),
        );
    }
}
