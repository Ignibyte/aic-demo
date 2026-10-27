<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Sales\Service\SaleCards;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/sales/{id}: a sale's page, with the notice recording it left; 404 for an id no sale has. The prefix is
 * protected, so only signed-in staff reach it.
 */
final readonly class SalePage implements Page
{
    /**
     * @param SaleCards $saleCards The sale's page.
     */
    public function __construct(
        private SaleCards $saleCards,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException When the sale cannot be read.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse The sale's page, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->saleCards->page($serverRequest);
    }
}
