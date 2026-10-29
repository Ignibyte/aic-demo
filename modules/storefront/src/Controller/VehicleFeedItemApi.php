<?php

declare(strict_types=1);

namespace App\Storefront\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use App\Inventory\Entity\ListedVehicle;
use App\Storefront\Service\CarFinder;
use App\Storefront\Service\FeedVehicles;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /api/vehicles/{slug}: one vehicle for sale as JSON, as the feed gives it; 404 as JSON for one sold, on hold or
 * unknown.
 */
final readonly class VehicleFeedItemApi implements Api
{
    /**
     * @param CarFinder    $carFinder    Finds the car the slug names.
     * @param FeedVehicles $feedVehicles The feed.
     */
    public function __construct(
        private CarFinder $carFinder,
        private FeedVehicles $feedVehicles,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the slug.
     *
     * @throws LogicException   When an entity type is malformed.
     * @throws RuntimeException When the vehicles or their photos cannot be read.
     *
     * @return ApiResponse The vehicle, or the 404.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        $listedVehicle = $this->carFinder->car($serverRequest);

        return $listedVehicle instanceof ListedVehicle
            ? new JsonResponse($this->feedVehicles->vehicle($listedVehicle))
            : new JsonResponse($this->feedVehicles->missing(), 404);
    }
}
