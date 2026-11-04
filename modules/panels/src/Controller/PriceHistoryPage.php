<?php

declare(strict_types=1);

namespace App\Panels\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Panels\Service\PanelPages;
use App\Panels\Service\PriceHistoryPanel;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /app/inventory/{vin}/price-history (#31): a vehicle's price history panel, which its page's skeleton loads and
 * the arrived panel polls; 204 when nothing changed, 404 for a VIN no vehicle has. The prefix is protected, so only
 * signed-in staff reach it.
 */
final readonly class PriceHistoryPage implements Page
{
    /**
     * @param PanelPages        $panelPages        The panels' routes' flow.
     * @param PriceHistoryPanel $priceHistoryPanel The panel.
     */
    public function __construct(
        private PanelPages $panelPages,
        private PriceHistoryPanel $priceHistoryPanel,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws RuntimeException When the vehicle or its price changes cannot be read.
     * @throws LogicException   When a key, an anchor or a status does not fit, or the props cannot be sealed.
     *
     * @return PageResponse The panel in the vehicle pages' frame, 204, 400 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->panelPages->answer($serverRequest, $this->priceHistoryPanel);
    }
}
