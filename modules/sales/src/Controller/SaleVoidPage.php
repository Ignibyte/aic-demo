<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Sales\Form\SaleVoids;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/sales/{id}/void: asks before a sale is voided; 404 for an id no sale has.
 */
final readonly class SaleVoidPage implements Page
{
    /**
     * @param SaleVoids $saleVoids The void's flows.
     */
    public function __construct(
        private SaleVoids $saleVoids,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException When the sale cannot be read.
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The page that asks first, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->saleVoids->confirm($serverRequest);
    }
}
