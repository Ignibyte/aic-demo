<?php

declare(strict_types=1);

namespace App\Sales\Form;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use App\Sales\Entity\Sale;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\SaleRecords;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffNotices;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Voiding a sale: the page that asks first, and the void itself, which removes the sale, puts its vehicle back in
 * stock, leaves a notice for the vehicle's page and goes there (303).
 */
final readonly class SaleVoids
{
    /**
     * @param SaleRecords          $saleRecords          The route's sale and the 404 and 400 pages.
     * @param SaleRecordRepository $saleRecordRepository Voids the sale.
     * @param SaleVoidForm         $saleVoidForm         The void page.
     * @param StaffSeals           $staffSeals           Checks the form's envelope.
     * @param StaffNotices         $staffNotices         Leaves the void's notice for the vehicle's page.
     */
    public function __construct(
        private SaleRecords $saleRecords,
        private SaleRecordRepository $saleRecordRepository,
        private SaleVoidForm $saleVoidForm,
        private StaffSeals $staffSeals,
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a sale's void page, with its session.
     *
     * @throws QueryException When the sale cannot be read.
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The page that asks first, or 404.
     */
    public function confirm(ServerRequestInterface $serverRequest): PageResponse
    {
        $sale = $this->saleRecords->find($serverRequest);

        if (!$sale instanceof Sale) {
            return $this->saleRecords->missing();
        }

        return $this->saleVoidForm->page($serverRequest, $sale);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted void form, with its session.
     *
     * @throws Throwable When the sale cannot be read or voided (the transaction rolled back), the request has no session,
     *                   or a key does not fit the theme.
     *
     * @return PageResponse 303 to the vehicle's page; 400 for an envelope that is not this sale's; or 404, also when the
     *                      sale went meanwhile.
     */
    public function void(ServerRequestInterface $serverRequest): PageResponse
    {
        $sale = $this->saleRecords->find($serverRequest);

        if (!$sale instanceof Sale) {
            return $this->saleRecords->missing();
        }

        if (!$this->staffSeals->holds($serverRequest, $sale->path())) {
            return $this->saleRecords->refused();
        }

        if (!$this->saleRecordRepository->void($sale)) {
            return $this->saleRecords->missing();
        }

        $this->staffNotices->keep($serverRequest, 'Voided the sale; the vehicle is back in stock.');

        return new PageRedirect($sale->vehiclePath());
    }
}
