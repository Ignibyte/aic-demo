<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Entity\Application\EntityStore;
use AIC\Module\Attribute\Tagged;
use App\Inventory\Form\VehicleChecks;
use App\Inventory\Form\VehicleDeleteForm;
use App\Inventory\Form\VehicleDeletes;
use App\Inventory\Form\VehicleForm;
use App\Inventory\Form\VehicleSaves;
use App\Inventory\Repository\VehicleRecordRepository;
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
 * reach the classes the wiring builds: `VehiclePhotoForms` takes `vehicleRecords()` and `vehicleCards()`.
 */
final readonly class VehicleServices
{
    /**
     * @param EntityStore  $entityStore  The entity store.
     * @param StaffPages   $staffPages   The staff pages' document.
     * @param StaffSeals   $staffSeals   Seals and checks a record's path in its forms' envelopes.
     * @param list<object> $vehicleSales The `VehicleSales` plugins, in tag order (`#[Tagged]`).
     */
    public function __construct(
        private EntityStore $entityStore,
        private StaffPages $staffPages,
        private StaffSeals $staffSeals,
        #[Tagged(VehicleSales::class)]
        private array $vehicleSales,
    ) {
    }

    /**
     * @return VehicleSaves The form's flows.
     */
    public function vehicleSaves(): VehicleSaves
    {
        $vehicleRecordRepository = $this->repository();
        $staffPanels = $this->panels();
        $vehicleForm = new VehicleForm($staffPanels, $this->staffSeals);
        $vehicleRecords = new VehicleRecords($vehicleRecordRepository, $staffPanels);
        $vehicleChecks = new VehicleChecks($vehicleRecordRepository, $this->soldVehicles());

        return new VehicleSaves($vehicleRecords, $vehicleRecordRepository, $vehicleChecks, $vehicleForm, $this->staffSeals);
    }

    /**
     * @return VehicleDeletes The delete's flows.
     */
    public function vehicleDeletes(): VehicleDeletes
    {
        $staffPanels = $this->panels();
        $vehicleRecordRepository = $this->repository();
        $vehicleDeleteForm = new VehicleDeleteForm($staffPanels, $this->staffSeals);
        $vehicleRecords = new VehicleRecords($vehicleRecordRepository, $staffPanels);

        return new VehicleDeletes(
            $vehicleRecords,
            $vehicleRecordRepository,
            $vehicleDeleteForm,
            $this->staffSeals,
            $this->soldVehicles(),
            $this->vehiclePhotos(),
        );
    }

    /**
     * @return VehicleCards The vehicle's page, its photos with it.
     */
    public function vehicleCards(): VehicleCards
    {
        $staffPanels = $this->panels();
        $vehicleRecords = new VehicleRecords($this->repository(), $staffPanels);
        $photoGalleries = new PhotoGalleries($this->vehiclePhotos(), $this->staffSeals);

        return new VehicleCards($vehicleRecords, $staffPanels, $this->soldVehicles(), $photoGalleries);
    }

    /**
     * @return VehicleRecords The route's vehicle, its id, and the vehicle pages' 404 and warning pages.
     */
    public function vehicleRecords(): VehicleRecords
    {
        return new VehicleRecords($this->repository(), $this->panels());
    }

    /**
     * @return VehicleRecordRepository One vehicle at a time, by VIN.
     */
    private function repository(): VehicleRecordRepository
    {
        return new VehicleRecordRepository($this->entityStore);
    }

    /**
     * @return SoldVehicles Asks the `VehicleSales` plugins about a vehicle's sale.
     */
    private function soldVehicles(): SoldVehicles
    {
        return new SoldVehicles($this->entityStore, $this->vehicleSales);
    }

    /**
     * @return VehiclePhotos A vehicle's photos, by its id.
     */
    private function vehiclePhotos(): VehiclePhotos
    {
        return new VehiclePhotos($this->entityStore);
    }

    /**
     * @return StaffPanels The vehicle pages' frame in the staff pages' document.
     */
    private function panels(): StaffPanels
    {
        return new StaffPanels($this->staffPages, 'vehicle');
    }
}
