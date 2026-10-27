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
 * POST /app/sales/{id}/void: removes the sale and puts its vehicle back in stock, then goes to the vehicle (303); 400
 * when the form's envelope is not this sale's, 404 when no sale has the id.
 */
final readonly class SaleVoidPost implements Page
{
    /**
     * @param SaleVoids $saleVoids The void's flows.
     */
    public function __construct(
        private SaleVoids $saleVoids,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException When the sale cannot be read or voided.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the vehicle, 400 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->saleVoids->void($serverRequest);
    }
}
