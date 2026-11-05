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
 * GET /cars/{slug}/brochure (#44): a car for sale's one-page brochure, sent inline as HTML from AIC's private files once
 * the `car-brochure` job has kept it, so the browser opens it with its print styles; AIC answers its ETag, conditions
 * and byte range. 404 for a car not for sale or no brochure yet.
 */
final readonly class CarBrochureStream implements Stream
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
     * @return StreamResponse The brochure, inline.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->carFinder->brochure($serverRequest, false);
    }
}
