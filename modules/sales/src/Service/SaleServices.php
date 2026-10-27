<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Controller\Application\PageDocuments;
use AIC\Database\Contracts\Database;
use App\Sales\Form\SaleChecks;
use App\Sales\Form\SaleFields;
use App\Sales\Form\SaleForm;
use App\Sales\Form\SaleSaves;
use App\Sales\Repository\SaleChoiceRepository;
use App\Sales\Repository\SaleRecordRepository;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;

/**
 * The factory the manifest names for the sale form's services, built from the database and the page documents, so the
 * generated handler parts make one call apiece rather than each writing out the graph the controllers share (gate:14).
 * `SaleRecordServices` builds the sale page's and the void's.
 */
final readonly class SaleServices
{
    /**
     * @param Database             $database             The database.
     * @param PageDocuments        $pageDocuments        The theme's document around a page.
     * @param SaleRecordRepository $saleRecordRepository One sale at a time, changing its vehicle's status through
     *                                                   inventory.
     */
    public function __construct(
        private Database $database,
        private PageDocuments $pageDocuments,
        private SaleRecordRepository $saleRecordRepository,
    ) {
    }

    /**
     * @return SaleSaves The form's flows.
     */
    public function saleSaves(): SaleSaves
    {
        $saleChoiceRepository = new SaleChoiceRepository($this->database);
        $saleForm = new SaleForm($this->staffPanels(), new SaleFields($saleChoiceRepository));

        return new SaleSaves($saleChoiceRepository, $this->saleRecordRepository, new SaleChecks($saleChoiceRepository), $saleForm);
    }

    /**
     * @return StaffPanels The sale pages' frame in the staff pages' document.
     */
    private function staffPanels(): StaffPanels
    {
        return new StaffPanels(new StaffPages($this->pageDocuments), 'sale');
    }
}
