<?php

declare(strict_types=1);

namespace App\Customers\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Customers\Form\CustomerDeletes;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /app/customers/{id}/delete: deletes the customer when the envelope seals its path, then goes to the list
 * (303); else 400 for the envelope, or 404.
 */
final readonly class CustomerDeletePost implements Page
{
    /**
     * @param CustomerDeletes $customerDeletes The delete's flows.
     */
    public function __construct(
        private CustomerDeletes $customerDeletes,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException When the customer cannot be read or deleted.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the list, 400 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->customerDeletes->delete($serverRequest);
    }
}
