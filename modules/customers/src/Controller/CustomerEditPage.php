<?php

declare(strict_types=1);

namespace App\Customers\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Customers\Form\CustomerSaves;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/customers/{id}/edit: the form filled from the customer, its envelope sealing the customer's path; 404
 * for an id no customer has.
 */
final readonly class CustomerEditPage implements Page
{
    /**
     * @param CustomerSaves $customerSaves The form's flows.
     */
    public function __construct(
        private CustomerSaves $customerSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException When the customer cannot be read.
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The filled form, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->customerSaves->edit($serverRequest);
    }
}
