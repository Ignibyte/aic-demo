<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Inventory\Form\VehiclePhotoForms;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * POST /app/inventory/{vin}/photos/{position}/delete: deletes the photo at that position and its file when a sales
 * manager or an admin sent it and its envelope seals that photo, then goes back to the vehicle (303); else 400 for
 * the envelope, 403 for a salesperson, or 404.
 */
final readonly class VehiclePhotoDeletePost implements Page
{
    /**
     * @param VehiclePhotoForms $vehiclePhotoForms The photos' flows.
     */
    public function __construct(
        private VehiclePhotoForms $vehiclePhotoForms,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted delete form, with its session.
     *
     * @throws LogicException   When the request has no session, or a status does not fit the theme.
     * @throws RuntimeException When the vehicle or its photos cannot be read or deleted, or a stored status or the
     *                          member's role is not one.
     *
     * @return PageResponse 303 to the vehicle, 400, 403 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehiclePhotoForms->remove($serverRequest);
    }
}
