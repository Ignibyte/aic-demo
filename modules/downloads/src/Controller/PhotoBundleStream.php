<?php

declare(strict_types=1);

namespace App\Downloads\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use App\Downloads\Service\PhotoBundleDownloads;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /app/inventory/{vin}/photos.zip (#36): the vehicle's photos as one ZIP, for a signed-in member of staff, saved as
 * `{VIN}-photos.zip` from AIC's private files once the `photo-bundle` job has written it; AIC answers its ETag,
 * conditions, `If-Range` and byte range (206, 304, 412, 416). No vehicle, no bundle or no member answers 404.
 */
final readonly class PhotoBundleStream implements Stream
{
    /**
     * @param PhotoBundleDownloads $photoBundleDownloads The bundle's download.
     */
    public function __construct(
        private PhotoBundleDownloads $photoBundleDownloads,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff, routed with the VIN.
     *
     * @throws LogicException   When the request carries no session, or an entity type is malformed.
     * @throws RuntimeException When the vehicle cannot be read, or there is no bundle to send
     *                          (`RouteNotFoundException`, which AIC answers 404).
     *
     * @return StreamResponse The ZIP.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->photoBundleDownloads->download($serverRequest);
    }
}
