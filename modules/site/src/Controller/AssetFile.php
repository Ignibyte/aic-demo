<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Routing\Domain\RouteNotFoundException;
use App\Site\Service\RequestedAsset;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/** Serve the built stylesheet and pinned Tessera files on the native runtime. */
final readonly class AssetFile implements Stream
{
    /** @param RequestedAsset $requestedAsset Selects only approved built files. */
    public function __construct(private RequestedAsset $requestedAsset)
    {
    }

    /**
     * @param ServerRequestInterface $serverRequest The incoming asset request.
     *
     * @throws RouteNotFoundException When the URL is not a declared built asset.
     *
     * @return StreamResponse The asset inline, with byte-range support.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->requestedAsset->inline($serverRequest);
    }
}
