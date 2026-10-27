<?php

declare(strict_types=1);

namespace App\Customers\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Customers\Service\CustomerCards;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/customers/{id}: a customer's page, with the notice its last save left; 404 for an id no customer has.
 * The prefix is protected, so only signed-in staff reach it.
 */
final readonly class CustomerPage implements Page
{
    /**
     * @param CustomerCards $customerCards The customer's page.
     */
    public function __construct(
        private CustomerCards $customerCards,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException When the customer cannot be read.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse The customer's page, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->customerCards->page($serverRequest);
    }
}
