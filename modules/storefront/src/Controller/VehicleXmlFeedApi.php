<?php

declare(strict_types=1);

namespace App\Storefront\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\XmlResponse;
use App\Storefront\Service\Feed\VehicleXmlFeed;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /feeds/vehicles.xml (#37): every car for sale as XML for listing sites, each with its VIN, the one public answer
 * that carries one. It takes the request, so AIC answers it with a strong ETag and a matching `If-None-Match` with 304
 * (AIC #269).
 */
final readonly class VehicleXmlFeedApi implements Api
{
    /**
     * @param VehicleXmlFeed $vehicleXmlFeed The feed.
     */
    public function __construct(
        private VehicleXmlFeed $vehicleXmlFeed,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, whose `If-None-Match` the answer honours.
     *
     * @throws LogicException   When an entity type is malformed, or a value holds a character XML 1.0 forbids.
     * @throws RuntimeException When the cars or their photos cannot be read.
     *
     * @return ApiResponse The cars, as XML.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new XmlResponse($this->vehicleXmlFeed->document(), 200, $serverRequest);
    }
}
