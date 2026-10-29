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
 * POST /app/inventory/{vin}/photos: adds the posted photo when a sales manager or an admin sent it and AIC's
 * `UploadCheck` accepts its content, then goes back to the vehicle (303); else the vehicle's page with the reason
 * (422), 403 for a salesperson, or 404.
 */
final readonly class VehiclePhotoPost implements Page
{
    /**
     * @param VehiclePhotoForms $vehiclePhotoForms The photos' flows.
     */
    public function __construct(
        private VehiclePhotoForms $vehiclePhotoForms,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted multipart form, with its session.
     *
     * @throws LogicException   When the request has no session, or a key does not fit the theme.
     * @throws RuntimeException When the vehicle or its photos cannot be read or written, a stored status or the
     *                          member's role is not one, or the upload cannot be read or moved.
     *
     * @return PageResponse 303 to the vehicle, 422, 403 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehiclePhotoForms->add($serverRequest);
    }
}
