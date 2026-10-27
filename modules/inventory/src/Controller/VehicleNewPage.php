<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Form\VehicleSaves;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/inventory/new: the blank vehicle form, in stock and on the lot from the database's today. The prefix is
 * protected, so only signed-in staff reach it.
 */
final readonly class VehicleNewPage implements Page
{
    /**
     * @param VehicleSaves $vehicleSaves The form's flows.
     */
    public function __construct(
        private VehicleSaves $vehicleSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException When the database's date cannot be read.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse The blank form.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleSaves->start($serverRequest);
    }
}
