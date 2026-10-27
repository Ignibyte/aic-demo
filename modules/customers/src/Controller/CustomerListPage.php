<?php

declare(strict_types=1);

namespace App\Customers\Controller;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Customers\Service\CustomerList;
use App\Staff\Service\StaffPages;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/customers: Ridgeway Motors' customers, searched and paged by plain GET parameters. The prefix is
 * protected, so only signed-in staff reach it.
 */
final readonly class CustomerListPage implements Page
{
    /**
     * @param StaffPages   $staffPages   The staff pages' document.
     * @param CustomerList $customerList The list.
     */
    public function __construct(
        private StaffPages $staffPages,
        private CustomerList $customerList,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException When the customers cannot be read.
     * @throws LogicException When the request has no session, or a key or a region does not fit the theme.
     *
     * @return PageResponse The list.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $panel = $this->customerList->panel($serverRequest);

        return $this->staffPages->page('Customers', [new KeyedComponent('customers', $panel)], 200);
    }
}
