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
 * POST /app/customers: makes the customer when every field passes and goes to its page (303); else the form again
 * (422), each error beside its field.
 */
final readonly class CustomerCreatePost implements Page
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
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the new customer, or 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->customerSaves->create($serverRequest);
    }
}
