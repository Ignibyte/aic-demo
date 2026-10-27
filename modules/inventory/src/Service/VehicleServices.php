<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Controller\Application\PageDocuments;
use AIC\Entity\Application\EntityStore;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Module\Attribute\Tagged;
use App\Inventory\Form\VehicleChecks;
use App\Inventory\Form\VehicleDeleteForm;
use App\Inventory\Form\VehicleDeletes;
use App\Inventory\Form\VehicleForm;
use App\Inventory\Form\VehicleSaves;
use App\Inventory\Repository\VehicleRecordRepository;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;

/**
 * The factory the manifest names for the vehicle record's services: each from the entity store, the envelope codec, the
 * page documents and the `VehicleSales` plugins. The build splits the module's handlers into parts and each part
 * builds what its controllers take, so a graph the controllers share would be written out in every part; through here
 * each part makes one call.
 */
final readonly class VehicleServices
{
    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param EnvelopeCodec $envelopeCodec Seals and opens envelopes with the app secret.
     * @param PageDocuments $pageDocuments The theme's document around a page.
     * @param list<object>  $vehicleSales  The `VehicleSales` plugins, in tag order (`#[Tagged]`).
     */
    public function __construct(
        private EntityStore $entityStore,
        private EnvelopeCodec $envelopeCodec,
        private PageDocuments $pageDocuments,
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
        $staffSeals = new StaffSeals($this->envelopeCodec);
        $vehicleForm = new VehicleForm($staffPanels, $staffSeals);
        $vehicleRecords = new VehicleRecords($vehicleRecordRepository, $staffPanels);
        $vehicleChecks = new VehicleChecks($vehicleRecordRepository, $this->soldVehicles());

        return new VehicleSaves($vehicleRecords, $vehicleRecordRepository, $vehicleChecks, $vehicleForm, $staffSeals);
    }

    /**
     * @return VehicleDeletes The delete's flows.
     */
    public function vehicleDeletes(): VehicleDeletes
    {
        $staffSeals = new StaffSeals($this->envelopeCodec);
        $staffPanels = $this->panels();
        $vehicleRecordRepository = $this->repository();
        $vehicleDeleteForm = new VehicleDeleteForm($staffPanels, $staffSeals);
        $vehicleRecords = new VehicleRecords($vehicleRecordRepository, $staffPanels);

        return new VehicleDeletes($vehicleRecords, $vehicleRecordRepository, $vehicleDeleteForm, $staffSeals, $this->soldVehicles());
    }

    /**
     * @return VehicleCards The vehicle's page.
     */
    public function vehicleCards(): VehicleCards
    {
        $staffPanels = $this->panels();

        $vehicleRecords = new VehicleRecords($this->repository(), $staffPanels);

        return new VehicleCards($vehicleRecords, $staffPanels, $this->soldVehicles());
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
     * @return StaffPanels The vehicle pages' frame in the staff pages' document.
     */
    private function panels(): StaffPanels
    {
        return new StaffPanels(new StaffPages($this->pageDocuments), 'vehicle');
    }
}
