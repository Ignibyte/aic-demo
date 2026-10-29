<?php

declare(strict_types=1);

namespace App\Storefront\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use App\Storefront\Service\FeedVehicles;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /api/vehicles: the vehicles for sale as JSON for listing partners, the same as the public list in its first
 * order; `?make=` keeps one make.
 */
final readonly class VehicleFeedApi implements Api
{
    /**
     * @param FeedVehicles $feedVehicles The feed.
     */
    public function __construct(
        private FeedVehicles $feedVehicles,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its query parameters.
     *
     * @throws LogicException   When an entity type is malformed.
     * @throws RuntimeException When the vehicles or their photos cannot be read.
     *
     * @return ApiResponse The vehicles.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse($this->feedVehicles->list($serverRequest));
    }
}
