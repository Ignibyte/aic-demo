<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Controller\Application\PageDocuments;
use AIC\Entity\Application\EntityStore;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Module\Attribute\Tagged;
use App\Sales\Form\SaleVoidForm;
use App\Sales\Form\SaleVoids;
use App\Sales\Repository\SaleRecordRepository;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\DealershipNames;
use App\Staff\Service\StaffDealership;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;

/**
 * The factory the manifest names for a sale's own pages: the sale's page and its void, each built from the envelope codec,
 * the page documents and the sales repository, so the generated handler parts make one call apiece (gate:14).
 */
final readonly class SaleRecordServices
{
    /**
     * @param EnvelopeCodec        $envelopeCodec        Seals and opens envelopes with the app secret.
     * @param PageDocuments        $pageDocuments        The theme's document around a page.
     * @param SaleRecordRepository $saleRecordRepository One sale at a time, changing its vehicle's status through
     *                                                   inventory.
     * @param EntityStore          $entityStore          The entity store, through which the name is read.
     * @param list<object>         $dealershipNames      The `DealershipNames` plugins (`#[Tagged]`), which name the
     *                                                   dealership on the sale's page.
     */
    public function __construct(
        private EnvelopeCodec $envelopeCodec,
        private PageDocuments $pageDocuments,
        private SaleRecordRepository $saleRecordRepository,
        private EntityStore $entityStore,
        #[Tagged(DealershipNames::class)]
        private array $dealershipNames,
    ) {
    }

    /**
     * @return SaleVoids The void's flows.
     */
    public function saleVoids(): SaleVoids
    {
        $staffSeals = new StaffSeals($this->envelopeCodec);
        $staffPanels = $this->staffPanels();
        $saleRecords = new SaleRecords($this->saleRecordRepository, $staffPanels);

        return new SaleVoids($saleRecords, $this->saleRecordRepository, new SaleVoidForm($staffPanels, $staffSeals), $staffSeals);
    }

    /**
     * @return SaleCards The sale's page.
     */
    public function saleCards(): SaleCards
    {
        $staffPanels = $this->staffPanels();

        return new SaleCards(new SaleRecords($this->saleRecordRepository, $staffPanels), $staffPanels, $this->staffDealership());
    }

    /**
     * @return StaffDealership The dealership's name, from the first `DealershipNames` plugin.
     */
    private function staffDealership(): StaffDealership
    {
        return new StaffDealership($this->entityStore, $this->dealershipNames);
    }

    /**
     * @return StaffPanels A sale's pages' frame in the staff pages' document.
     */
    private function staffPanels(): StaffPanels
    {
        return new StaffPanels(new StaffPages($this->pageDocuments), 'sale');
    }
}
