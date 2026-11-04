<?php

declare(strict_types=1);

namespace App\Compare\Service;

use AIC\Entity\Application\EntityStore;
use AIC\Storage\Application\PublicFiles;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Photos\Service\VehiclePhotos;
use App\Storefront\Service\StorePages;

/**
 * The compare module's factory (#33): the comparison, built here from the cars, their photos and the store's frame, so
 * it reaches the generated wiring as one product and the generated handler names few classes, as storefront's
 * `StoreServices` does for the car's page.
 */
final readonly class CompareServices
{
    /**
     * @param EntityStore $entityStore The tables.
     * @param PublicFiles $publicFiles AIC's public files, where the photos are kept.
     * @param StorePages  $storePages  The store's frame and the dealership's profile.
     */
    public function __construct(
        private EntityStore $entityStore,
        private PublicFiles $publicFiles,
        private StorePages $storePages,
    ) {
    }

    /**
     * @return CarComparisons The comparison.
     */
    public function carComparisons(): CarComparisons
    {
        $comparisonTables = new ComparisonTables(
            new ListedVehicleRepository($this->entityStore),
            new VehiclePhotos($this->entityStore, $this->publicFiles),
        );

        return new CarComparisons($this->storePages, $comparisonTables);
    }
}
