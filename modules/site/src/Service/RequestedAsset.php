<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Domain\FileDownload;
use AIC\Routing\Domain\RouteNotFoundException;
use Psr\Http\Message\ServerRequestInterface;

/** Select configured built files; an untrusted URL never forms a filesystem path. */
final readonly class RequestedAsset
{
    /**
     * @param string    $root      The application root supplied by generated wiring.
     * @param AppConfig $appConfig The same validated Tessera pin used by the build and page.
     */
    public function __construct(
        private string $root,
        private AppConfig $appConfig,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The requested URL and byte range.
     *
     * @throws RouteNotFoundException When the URL is unknown, missing or traversing.
     *
     * @return FileDownload The exact built file, sent inline.
     */
    public function inline(ServerRequestInterface $serverRequest): FileDownload
    {
        $range = $serverRequest->getHeaderLine('Range');
        $scriptPath = $this->appConfig->tessera->scriptPath();
        $mapPath = $scriptPath . '.map';

        return match ($serverRequest->getUri()->getPath()) {
            '/assets/aic.css' => new FileDownload($this->root . '/public/assets/aic.css', 'text/css; charset=utf-8', '', $range),
            $scriptPath => new FileDownload(
                $this->root . '/public' . $scriptPath,
                'text/javascript; charset=utf-8',
                '',
                $range,
            ),
            $mapPath => new FileDownload(
                $this->root . '/public' . $mapPath,
                'application/json; charset=utf-8',
                '',
                $range,
            ),
            default => throw new RouteNotFoundException('Unknown built asset.'),
        };
    }
}
