<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Controller\Application\PageDocuments;
use AIC\Entity\Application\EntityStore;
use AIC\Module\Attribute\Tagged;
use App\Brochures\Service\Brochures;
use App\Finance\Service\DealershipRates;
use App\Finance\Service\FinanceRates;
use App\Inventory\Entity\VehiclePanel;
use App\Inventory\Form\VehicleChecks;
use App\Inventory\Form\VehicleDeletes;
use App\Inventory\Form\VehicleSaves;
use App\Photos\Service\PhotoGalleries;
use App\Photos\Service\VehiclePhotos;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;

/**
 * The factory the manifest names for the vehicle record's services: each from the entity store, the staff pages'
 * document, the envelope seal and the `VehicleSales` plugins, with photos' services for the vehicle's photos (#16). The
 * build splits the module's handlers into parts and each part builds what its controllers take, so a graph the
 * controllers share would be written out in every part; through here each part makes one call. Its products also
 * reach the classes the wiring builds: `VehiclePhotoForms` takes `vehicleRecords()` and `vehicleCards()`. It builds
 * the staff pages from the page documents, so the seven vehicle routes' generated part names few classes, and takes
 * the photos' service, whose files are in AIC's public files (#23), as the wiring builds it for the photo forms. The
 * vehicle form queues the car's brochure after a save (#44). The vehicle's page shows finance's
 * payment calculator (#26) at the dealership's APR, which the first `FinanceRates` plugin gives. It takes staff's
 * seals as the wiring builds them rather than the envelope codec, so the two generated parts that build it (one
 * opening with the record's forms, one with the photos') do not repeat one statement long enough for gate:14. The
 * vehicle's page places the `VehiclePanel` plugins' panels (#31), which `VehicleRecords` asks, each sealing its version
 * through staff's seals.
 */
final readonly class VehicleServices
{
    /**
     * The staff pages' document.
     */
    private StaffPages $staffPages;

    /**
     * @param EntityStore    $entityStore    The entity store.
     * @param PageDocuments  $pageDocuments  The theme's documents.
     * @param StaffSeals     $staffSeals     Seals and checks a record's path in its forms' envelopes.
     * @param list<object>   $vehicleSales   The `VehicleSales` plugins, in tag order (`#[Tagged]`).
     * @param VehiclePhotos  $vehiclePhotos  A vehicle's photos, by its id, their files in AIC's public files.
     * @param list<object>   $financeRates   The `FinanceRates` plugins, in tag order (`#[Tagged]`).
     * @param list<object>   $vehiclePanels  The `VehiclePanel` plugins, in tag order (`#[Tagged]`).
     * @param PhotoGalleries $photoGalleries The vehicle's photo gallery, with its ZIP's link (#36).
     * @param Brochures      $brochures      Queues a car's brochure after the form saves it (#44).
     */
    public function __construct(
        private EntityStore $entityStore,
        PageDocuments $pageDocuments,
        private StaffSeals $staffSeals,
        #[Tagged(VehicleSales::class)]
        private array $vehicleSales,
        private VehiclePhotos $vehiclePhotos,
        #[Tagged(FinanceRates::class)]
        private array $financeRates,
        #[Tagged(VehiclePanel::class)]
        private array $vehiclePanels,
        private PhotoGalleries $photoGalleries,
        private Brochures $brochures,
    ) {
        $this->staffPages = new StaffPages($pageDocuments);
    }

    /**
     * @return VehicleSaves The form's flows.
     */
    public function vehicleSaves(): VehicleSaves
    {
        $vehicleRecords = $this->vehicleRecords();
        $vehicleChecks = new VehicleChecks($vehicleRecords->repository(), $this->soldVehicles());

        return new VehicleSaves($vehicleRecords, $vehicleChecks, $this->staffSeals, $this->brochures);
    }

    /**
     * @return VehicleDeletes The delete's flows.
     */
    public function vehicleDeletes(): VehicleDeletes
    {
        $vehicleRecords = $this->vehicleRecords();
        $vehicleRecordRepository = $vehicleRecords->repository();

        return new VehicleDeletes(
            $vehicleRecords,
            $vehicleRecordRepository,
            $this->staffSeals,
            $this->soldVehicles(),
            $this->vehiclePhotos,
        );
    }

    /**
     * @return VehicleCards The vehicle's page, its photos and its payment calculator with it.
     */
    public function vehicleCards(): VehicleCards
    {
        $vehicleRecords = $this->vehicleRecords();
        $dealershipRates = new DealershipRates($this->entityStore, $this->financeRates);

        return new VehicleCards($vehicleRecords, $this->soldVehicles(), $this->photoGalleries, $dealershipRates);
    }

    /**
     * @return VehicleRecords The route's vehicle, its id, and the vehicle pages' 404 and warning pages.
     */
    public function vehicleRecords(): VehicleRecords
    {
        return new VehicleRecords($this->entityStore, $this->panels(), $this->staffSeals, $this->vehiclePanels);
    }

    /**
     * @return SoldVehicles Asks the `VehicleSales` plugins about a vehicle's sale.
     */
    private function soldVehicles(): SoldVehicles
    {
        return new SoldVehicles($this->entityStore, $this->vehicleSales);
    }

    /**
     * @return StaffPanels The vehicle pages' frame in the staff pages' document.
     */
    private function panels(): StaffPanels
    {
        return new StaffPanels($this->staffPages, 'vehicle');
    }
}
