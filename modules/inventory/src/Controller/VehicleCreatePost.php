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
use UnexpectedValueException;

/**
 * POST /app/inventory: makes the vehicle when every field passes, then goes to its page (303); else the form again
 * (422), each error beside its field and every value as typed.
 */
final readonly class VehicleCreatePost implements Page
{
    /**
     * @param VehicleSaves $vehicleSaves The form's flows.
     */
    public function __construct(
        private VehicleSaves $vehicleSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException           When the vehicles cannot be read or written.
     * @throws UnexpectedValueException When the checked status is no status.
     * @throws LogicException           When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the new vehicle, or 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleSaves->create($serverRequest);
    }
}
