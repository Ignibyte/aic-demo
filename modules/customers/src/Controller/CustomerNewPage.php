<?php

declare(strict_types=1);

namespace App\Customers\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Customers\Form\CustomerSaves;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/customers/new: the blank customer form. The prefix is protected, so only signed-in staff reach it.
 */
final readonly class CustomerNewPage implements Page
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
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse The blank form.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->customerSaves->start($serverRequest);
    }
}
