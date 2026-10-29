<?php

declare(strict_types=1);

namespace App\Storefront\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use App\Storefront\Service\CarFinder;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /cars/{slug}/photos/{position}: a car for sale's photo at that position, from 1, sent inline with its media type;
 * AIC answers its ETag, conditions and byte range. 404 for a car not for sale or no photo there.
 */
final readonly class CarPhotoStream implements Stream
{
    /**
     * @param CarFinder $carFinder Finds the car and its photo.
     */
    public function __construct(
        private CarFinder $carFinder,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the slug and the position.
     *
     * @throws LogicException   When an entity type is malformed.
     * @throws RuntimeException When the vehicles or their photos cannot be read, or there is no such photo
     *                          (`RouteNotFoundException`, which AIC answers 404).
     *
     * @return StreamResponse The photo.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->carFinder->photo($serverRequest);
    }
}
