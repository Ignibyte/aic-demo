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
 * POST /app/customers/{id}: saves the edit form when its envelope seals this customer's path and every field
 * passes, then goes to the customer (303); else the form again (422), 400 for the envelope, or 404.
 */
final readonly class CustomerUpdatePost implements Page
{
    /**
     * @param CustomerSaves $customerSaves The form's flows.
     */
    public function __construct(
        private CustomerSaves $customerSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException When the customers cannot be read or written.
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the customer, 422, 400 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->customerSaves->update($serverRequest);
    }
}
