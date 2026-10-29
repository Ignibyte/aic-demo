<?php

declare(strict_types=1);

namespace App\Customers\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Customers\Form\CustomerDeletes;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /app/customers/{id}/delete: asks before the customer's record is deleted; 404 for an id no customer has.
 */
final readonly class CustomerDeletePage implements Page
{
    /**
     * @param CustomerDeletes $customerDeletes The delete's flows.
     */
    public function __construct(
        private CustomerDeletes $customerDeletes,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws RuntimeException When the customer or the dealership's name cannot be read.
     * @throws LogicException   When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The page that asks first, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->customerDeletes->confirm($serverRequest);
    }
}
