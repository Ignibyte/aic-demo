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
 * PUT /app/customers/{id:int}/row (#28): saves the row's phone when its props seal this customer's path and the
 * customer form's checks pass, then answers the row shown; else the row as a form again (422), 400 for the props, or
 * 404. AIC's form guard has checked the CSRF token before it is routed.
 */
final readonly class CustomerRowPut implements Page
{
    /**
     * @param CustomerRowEdits $customerRowEdits The row's flows.
     */
    public function __construct(
        private CustomerRowEdits $customerRowEdits,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The row's save, with its phone, props and session.
     *
     * @throws QueryException When the customer cannot be read or written.
     * @throws LogicException When the request has no session, a key or a value does not fit, or a region does not fit the theme.
     *
     * @return PageResponse The row shown, 422, 400 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->customerRowEdits->save($serverRequest);
    }
}
