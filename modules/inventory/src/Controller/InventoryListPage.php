<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Service\InventoryList;
use App\Staff\Service\StaffPages;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app/inventory: the vehicles on the lot, searched, filtered, sorted and paged by plain GET parameters. The
 * prefix is protected, so only signed-in staff reach it.
 */
final readonly class InventoryListPage implements Page
{
    /**
     * @param StaffPages    $staffPages    The staff pages' document.
     * @param InventoryList $inventoryList The list.
     */
    public function __construct(
        private StaffPages $staffPages,
        private InventoryList $inventoryList,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException           When the vehicles cannot be read.
     * @throws UnexpectedValueException When a stored status is no status.
     * @throws LogicException           When the request has no session, or a key or a region does not fit the theme.
     *
     * @return PageResponse The list.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $panel = $this->inventoryList->panel($serverRequest);

        return $this->staffPages->page('Inventory', [new KeyedComponent('vehicles', $panel)], 200);
    }
}
