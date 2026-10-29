<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Controller\Application\PageDocuments;
use AIC\Entity\Application\EntityStore;
use App\Holds\Repository\HoldRepository;
use App\Inventory\Repository\TodayRepository;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Sales\Form\SaleChecks;
use App\Sales\Form\SaleFields;
use App\Sales\Form\SaleForm;
use App\Sales\Form\SaleSaves;
use App\Sales\Repository\SaleChoiceRepository;
use App\Sales\Repository\SaleRecordRepository;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;

/**
 * The factory the manifest names for the sale form's services, built from the entity store and the page documents, so the
 * generated handler parts make one call apiece rather than each writing out the graph the controllers share (gate:14).
 * `SaleRecordServices` builds the sale page's and the void's.
 */
final readonly class SaleServices
{
    /**
     * @param EntityStore          $entityStore          The entity store.
     * @param PageDocuments        $pageDocuments        The theme's document around a page.
     * @param SaleRecordRepository $saleRecordRepository One sale at a time, changing its vehicle's status through
     *                                                   inventory.
     */
    public function __construct(
        private EntityStore $entityStore,
        private PageDocuments $pageDocuments,
        private SaleRecordRepository $saleRecordRepository,
    ) {
    }

    /**
     * @return SaleSaves The form's flows.
     */
    public function saleSaves(): SaleSaves
    {
        $saleChoiceRepository = new SaleChoiceRepository($this->entityStore, new TodayRepository($this->entityStore));
        $saleForm = new SaleForm($this->staffPanels(), new SaleFields($saleChoiceRepository));

        $holdRepository = new HoldRepository($this->entityStore, new VehicleStatusRepository($this->entityStore));
        $saleChecks = new SaleChecks($saleChoiceRepository, $holdRepository);

        return new SaleSaves($saleChoiceRepository, $this->saleRecordRepository, $saleChecks, $saleForm);
    }

    /**
     * @return StaffPanels The sale pages' frame in the staff pages' document.
     */
    private function staffPanels(): StaffPanels
    {
        return new StaffPanels(new StaffPages($this->pageDocuments), 'sale');
    }
}
