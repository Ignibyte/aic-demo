<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use AIC\Entity\Application\EntityStore;
use AIC\Storage\Application\PublicFiles;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Leads\Service\LeadDesk;
use App\Photos\Service\VehiclePhotos;

/**
 * The storefront module's factory (#18): the car's page, which `CarPage` and both lead posts share. Built here, it
 * reaches the generated wiring as one product, so the three handlers fit one generated part instead of three that
 * each spell its whole tree (gate:14).
 */
final readonly class StoreServices
{
    /**
     * @param EntityStore $entityStore The tables.
     * @param StorePages  $storePages  The public pages' frame, the dealership's profile in its header and footer.
     * @param LeadDesk    $leadDesk    The lead forms, and a sent one recorded.
     * @param PublicFiles $publicFiles AIC's public files, where the photos are kept.
     */
    public function __construct(
        private EntityStore $entityStore,
        private StorePages $storePages,
        private LeadDesk $leadDesk,
        private PublicFiles $publicFiles,
    ) {
    }

    /**
     * @return CarPages A car's page, its lead forms with it.
     */
    public function carPages(): CarPages
    {
        $vehiclePhotos = new VehiclePhotos($this->entityStore, $this->publicFiles);
        $carFinder = new CarFinder(new ListedVehicleRepository($this->entityStore), $vehiclePhotos);

        return new CarPages($this->storePages, $carFinder, new CarProfiles($vehiclePhotos), $this->leadDesk);
    }
}
