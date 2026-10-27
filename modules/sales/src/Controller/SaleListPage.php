<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Sales\Service\SaleList;
use App\Staff\Service\StaffPages;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/sales: Ridgeway Motors' sales, filtered by month and salesperson and paged by plain GET parameters, with
 * the totals of what the filters keep. The prefix is protected, so only signed-in staff reach it.
 */
final readonly class SaleListPage implements Page
{
    /**
     * @param StaffPages $staffPages The staff pages' document.
     * @param SaleList   $saleList   The list.
     */
    public function __construct(
        private StaffPages $staffPages,
        private SaleList $saleList,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException When the sales cannot be read.
     * @throws LogicException When the request has no session, or a key or a region does not fit the theme.
     *
     * @return PageResponse The list.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $panel = $this->saleList->panel($serverRequest);

        return $this->staffPages->page('Sales', [new KeyedComponent('sales', $panel)], 200);
    }
}
