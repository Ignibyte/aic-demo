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
 * GET /app/customers/{id:int}/row (#28): the customer's row in the list, shown, for Cancel and Escape in its form;
 * the customers page holding that row alone, so Tessera gets the row. 404 for no such customer.
 */
final readonly class CustomerRowPage implements Page
{
    /**
     * @param CustomerRowEdits $customerRowEdits The row's flows.
     */
    public function __construct(
        private CustomerRowEdits $customerRowEdits,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for the row, from a signed-in member of staff.
     *
     * @throws QueryException When the customer cannot be read or written.
     * @throws LogicException When the request has no session, a key or a value does not fit, or a region does not fit the theme.
     *
     * @return PageResponse The row shown, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->customerRowEdits->shown($serverRequest);
    }
}
