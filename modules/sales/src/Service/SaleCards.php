<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Sales\Entity\Sale;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\DetailCard\ButtonLink;
use App\Theme\Components\DetailCard\ButtonTone;
use App\Theme\Components\DetailCard\DetailCard;
use App\Theme\Components\DetailCard\Fact;
use App\Theme\Components\StatusBadge\BadgeTone;
use App\Theme\Components\StatusBadge\StatusBadge;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function number_format;

/**
 * A sale's page: the notice recording it left, then its card with the vehicle, the customer, the salesperson, the
 * price and the day, and buttons to the vehicle, to the customer, to void it or back to the list.
 */
final readonly class SaleCards
{
    /**
     * @param SaleRecords  $saleRecords  The route's sale and the 404 page.
     * @param StaffPanels  $staffPanels  The sale pages' frame.
     * @param StaffNotices $staffNotices The recording's notice.
     */
    public function __construct(
        private SaleRecords $saleRecords,
        private StaffPanels $staffPanels,
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a sale's page, with its session.
     *
     * @throws QueryException When the sale cannot be read.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse The sale's page, or 404.
     */
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        // The notice is taken first, so a sale voided before its 303 landed leaves nothing behind.
        $body = $this->staffNotices->take($serverRequest, 'sale-notice');
        $sale = $this->saleRecords->find($serverRequest);

        if (!$sale instanceof Sale) {
            return $this->saleRecords->missing();
        }

        $body['card'] = $this->card($sale);
        $title = 'Sale of the ' . $sale->vehicle;

        return $this->staffPanels->page($title, 'Sale record', 'What Ridgeway Motors keeps on this sale.', $body, 200);
    }

    /**
     * @param Sale $sale A sale.
     *
     * @return DetailCard Its card.
     */
    private function card(Sale $sale): DetailCard
    {
        return new DetailCard('sale-title', 'Sale of the ' . $sale->vehicle, new StatusBadge('Sold', BadgeTone::Success, 'sale-badge'), [
            new Fact('VIN', $sale->vin, true),
            new Fact('Customer', $sale->customer, false),
            new Fact('Salesperson', $sale->salesperson, false),
            new Fact('Sale price', '$' . number_format($sale->price), false),
            new Fact('Sold on', $sale->soldOn, false),
        ], '', [
            new ButtonLink('The vehicle', $sale->vehiclePath(), 'sale-vehicle-link', ButtonTone::Primary),
            new ButtonLink('The customer', $sale->customerPath(), 'sale-customer-link', ButtonTone::Secondary),
            new ButtonLink('Void', $sale->path() . '/void', 'sale-void', ButtonTone::Error),
            new ButtonLink('Back to the list', Sale::PAGES, 'sale-back', ButtonTone::Ghost),
        ]);
    }
}
