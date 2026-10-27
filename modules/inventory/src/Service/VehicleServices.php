<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Controller\Application\PageDocuments;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
use App\Inventory\Form\VehicleChecks;
use App\Inventory\Form\VehicleDeleteForm;
use App\Inventory\Form\VehicleDeletes;
use App\Inventory\Form\VehicleForm;
use App\Inventory\Form\VehicleSaves;
use App\Inventory\Form\VehicleSeals;
use App\Inventory\Repository\VehicleRecordRepository;
use App\Staff\Service\StaffPages;

/**
 * The factory the manifest names for the vehicle record's services: each from the database, the envelope codec and
 * the page documents. The build splits the module's handlers into parts and each part builds what its controllers
 * take, so a graph the controllers share would be written out in every part; through here each part makes one call.
 */
final readonly class VehicleServices
{
    /**
     * @param Database      $database      The database.
     * @param EnvelopeCodec $envelopeCodec Seals and opens envelopes with the app secret.
     * @param PageDocuments $pageDocuments The theme's document around a page.
     */
    public function __construct(
        private Database $database,
        private EnvelopeCodec $envelopeCodec,
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @return VehicleSaves The form's flows.
     */
    public function vehicleSaves(): VehicleSaves
    {
        $vehicleRecordRepository = $this->repository();
        $vehiclePanels = $this->panels();
        $vehicleSeals = new VehicleSeals($this->envelopeCodec);
        $vehicleForm = new VehicleForm($vehiclePanels, $vehicleSeals);
        $vehicleRecords = new VehicleRecords($vehicleRecordRepository, $vehiclePanels);
        $vehicleChecks = new VehicleChecks($vehicleRecordRepository);

        return new VehicleSaves($vehicleRecords, $vehicleRecordRepository, $vehicleChecks, $vehicleForm, $vehicleSeals);
    }

    /**
     * @return VehicleDeletes The delete's flows.
     */
    public function vehicleDeletes(): VehicleDeletes
    {
        $vehicleSeals = new VehicleSeals($this->envelopeCodec);
        $vehiclePanels = $this->panels();
        $vehicleRecordRepository = $this->repository();
        $vehicleDeleteForm = new VehicleDeleteForm($vehiclePanels, $vehicleSeals);
        $vehicleRecords = new VehicleRecords($vehicleRecordRepository, $vehiclePanels);

        return new VehicleDeletes($vehicleRecords, $vehicleRecordRepository, $vehicleDeleteForm, $vehicleSeals);
    }

    /**
     * @return VehicleCards The vehicle's page.
     */
    public function vehicleCards(): VehicleCards
    {
        $vehiclePanels = $this->panels();

        return new VehicleCards(new VehicleRecords($this->repository(), $vehiclePanels), $vehiclePanels);
    }

    /**
     * @return VehicleRecordRepository One vehicle at a time, by VIN.
     */
    private function repository(): VehicleRecordRepository
    {
        return new VehicleRecordRepository($this->database);
    }

    /**
     * @return VehiclePanels The vehicle pages' frame in the staff pages' document.
     */
    private function panels(): VehiclePanels
    {
        return new VehiclePanels(new StaffPages($this->pageDocuments));
    }
}
