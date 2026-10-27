<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Controller\Application\PageDocuments;
use AIC\Database\Contracts\Database;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Module\Attribute\Tagged;
use App\Customers\Form\CustomerChecks;
use App\Customers\Form\CustomerDeleteForm;
use App\Customers\Form\CustomerDeletes;
use App\Customers\Form\CustomerForm;
use App\Customers\Form\CustomerSaves;
use App\Customers\Repository\CustomerRecordRepository;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;

/**
 * The factory the manifest names for the customer record's services, each built from the database, the envelope
 * codec, the page documents and the `CustomerPurchases` plugins, so the generated handler parts make one call apiece
 * rather than each writing out the graph the controllers share (gate:14).
 */
final readonly class CustomerServices
{
    /**
     * @param Database      $database          The database.
     * @param EnvelopeCodec $envelopeCodec     Seals and opens envelopes with the app secret.
     * @param PageDocuments $pageDocuments     The theme's document around a page.
     * @param list<object>  $customerPurchases The `CustomerPurchases` plugins, in tag order (`#[Tagged]`).
     */
    public function __construct(
        private Database $database,
        private EnvelopeCodec $envelopeCodec,
        private PageDocuments $pageDocuments,
        #[Tagged(CustomerPurchases::class)]
        private array $customerPurchases,
    ) {
    }

    /**
     * @return CustomerSaves The form's flows.
     */
    public function customerSaves(): CustomerSaves
    {
        $customerRecordRepository = new CustomerRecordRepository($this->database);
        $staffPanels = $this->staffPanels();
        $staffSeals = new StaffSeals($this->envelopeCodec);

        return new CustomerSaves(
            new CustomerRecords($customerRecordRepository, $staffPanels),
            $customerRecordRepository,
            new CustomerChecks($customerRecordRepository),
            new CustomerForm($staffPanels, $staffSeals),
            $staffSeals,
        );
    }

    /**
     * @return CustomerDeletes The delete's flows.
     */
    public function customerDeletes(): CustomerDeletes
    {
        $staffSeals = new StaffSeals($this->envelopeCodec);
        $staffPanels = $this->staffPanels();
        $customerDeleteForm = new CustomerDeleteForm($staffPanels, $staffSeals);
        $customerRecordRepository = new CustomerRecordRepository($this->database);
        $customerRecords = new CustomerRecords($customerRecordRepository, $staffPanels);

        return new CustomerDeletes($customerRecords, $customerRecordRepository, $customerDeleteForm, $staffSeals, $this->boughtVehicles());
    }

    /**
     * @return CustomerCards The customer's page.
     */
    public function customerCards(): CustomerCards
    {
        $staffPanels = $this->staffPanels();

        $customerRecords = new CustomerRecords(new CustomerRecordRepository($this->database), $staffPanels);

        return new CustomerCards($customerRecords, $staffPanels, $this->boughtVehicles());
    }

    /**
     * @return BoughtVehicles Asks the `CustomerPurchases` plugins what a customer bought.
     */
    private function boughtVehicles(): BoughtVehicles
    {
        return new BoughtVehicles($this->database, $this->customerPurchases);
    }

    /**
     * @return StaffPanels The customer pages' frame in the staff pages' document.
     */
    private function staffPanels(): StaffPanels
    {
        return new StaffPanels(new StaffPages($this->pageDocuments), 'customer');
    }
}
