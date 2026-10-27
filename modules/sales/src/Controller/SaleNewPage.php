<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Sales\Form\SaleSaves;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/sales/new: the sale form, with the vehicle `?vehicle=` names chosen and its list price filled in when it is
 * on the lot and not sold. The prefix is protected, so only signed-in staff reach it.
 */
final readonly class SaleNewPage implements Page
{
    /**
     * @param SaleSaves $saleSaves The form's flows.
     */
    public function __construct(
        private SaleSaves $saleSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException When the vehicle or the options cannot be read.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse The form.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->saleSaves->start($serverRequest);
    }
}
