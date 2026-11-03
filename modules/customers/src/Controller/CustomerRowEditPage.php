<?php

declare(strict_types=1);

namespace App\Customers\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Customers\Form\CustomerRowEdits;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/customers/{id:int}/row/edit (#28): the customer's row in the list as a small form, for a click on its
 * phone; the customers page holding that row alone, so Tessera gets the row. 404 for no such customer.
 */
final readonly class CustomerRowEditPage implements Page
{
    /**
     * @param CustomerRowEdits $customerRowEdits The row's flows.
     */
    public function __construct(
        private CustomerRowEdits $customerRowEdits,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for the row as a form, from a signed-in member of staff.
     *
     * @throws QueryException When the customer cannot be read or written.
     * @throws LogicException When the request has no session, a key or a value does not fit, or a region does not fit the theme.
     *
     * @return PageResponse The row as a form, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->customerRowEdits->form($serverRequest);
    }
}
