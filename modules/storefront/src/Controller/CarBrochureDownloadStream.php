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
 * GET /cars/{slug}/brochure/download (#44): the same kept brochure as `/cars/{slug}/brochure`, sent as an attachment
 * named `{slug}-brochure.html` from the car's own slug; AIC answers its ETag, conditions and byte range. 404 for a car
 * not for sale or no brochure yet.
 */
final readonly class CarBrochureDownloadStream implements Stream
{
    /**
     * @param CarFinder $carFinder Finds the car and its brochure.
     */
    public function __construct(
        private CarFinder $carFinder,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the slug.
     *
     * @throws LogicException   When an entity type is malformed.
     * @throws RuntimeException When the vehicles cannot be read, or there is no brochure to send
     *                          (`RouteNotFoundException`, which AIC answers 404).
     *
     * @return StreamResponse The brochure, as an attachment.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->carFinder->brochure($serverRequest, true);
    }
}
