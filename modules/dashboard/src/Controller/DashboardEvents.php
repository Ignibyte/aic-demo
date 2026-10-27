<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\EventStream;
use App\Dashboard\Service\DashboardSources;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/events (#10): the dashboard's Tessera stream. It patches the tiles and the "Latest sales" table whenever the
 * sales change from what the client saw: `Last-Event-ID` on a reconnect, else `seen`, the fingerprint the page was drawn
 * with. `polls`, from 1 to 60, ends it after that many looks, for the native steps and the looks; without it the stream
 * lasts as long as the page. The protected prefix keeps it to signed-in staff.
 */
final readonly class DashboardEvents implements Stream
{
    /**
     * @param DashboardSources $dashboardSources Reads the request into the stream's source.
     */
    public function __construct(
        private DashboardSources $dashboardSources,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with `Last-Event-ID` on a reconnect.
     *
     * @return StreamResponse The events.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return new EventStream($this->dashboardSources->source($serverRequest));
    }
}
