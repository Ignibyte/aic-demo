<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use App\Inventory\Form\VehiclePhotoForms;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /app/inventory/{vin}/photos/{position}: the vehicle's photo at that position, from 1, sent inline with its media
 * type; AIC answers its ETag, conditions and byte range (206, 304, 412, 416). 404 for no vehicle or no photo there.
 */
final readonly class VehiclePhotoStream implements Stream
{
    /**
     * @param VehiclePhotoForms $vehiclePhotoForms The photos' flows.
     */
    public function __construct(
        private VehiclePhotoForms $vehiclePhotoForms,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws LogicException   When an entity type is malformed.
     * @throws RuntimeException When the vehicle or its photos cannot be read, or there is no such photo
     *                          (`RouteNotFoundException`, which AIC answers 404).
     *
     * @return StreamResponse The photo.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->vehiclePhotoForms->photo($serverRequest);
    }
}
