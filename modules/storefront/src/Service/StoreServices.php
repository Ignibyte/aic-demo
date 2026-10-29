<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use AIC\Controller\Application\PageDocuments;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Leads\Service\LeadDesk;
use App\Photos\Service\VehiclePhotos;
use App\Settings\Service\DealershipProfiles;

/**
 * The storefront module's factory (#18): the car's page, which `CarPage` and both lead posts share. Built here, it
 * reaches the generated wiring as one product, so the three handlers fit one generated part instead of three that
 * each spell its whole tree (gate:14).
 */
final readonly class StoreServices
{
    /**
     * @param EntityStore        $entityStore        The tables.
     * @param PageDocuments      $pageDocuments      The theme's documents.
     * @param LeadDesk           $leadDesk           The lead forms, and a sent one recorded.
     * @param DealershipProfiles $dealershipProfiles The dealership's profile, which the public header and footer show.
     */
    public function __construct(
        private EntityStore $entityStore,
        private PageDocuments $pageDocuments,
        private LeadDesk $leadDesk,
        private DealershipProfiles $dealershipProfiles,
    ) {
    }

    /**
     * @return CarPages A car's page, its lead forms with it.
     */
    public function carPages(): CarPages
    {
        $vehiclePhotos = new VehiclePhotos($this->entityStore);
        $carFinder = new CarFinder(new ListedVehicleRepository($this->entityStore), $vehiclePhotos);

        $storePages = new StorePages($this->pageDocuments, $this->dealershipProfiles);

        return new CarPages($storePages, $carFinder, new CarProfiles($vehiclePhotos), $this->leadDesk);
    }
}
