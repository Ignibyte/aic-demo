<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Site\Service\ResilienceWatches;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /theme/resilience/gateway (#34): the style guide's write with no answer. Its form posts to this path, which has
 * no POST route: `ResilienceFaults` answers every POST to it as the gateway in front of the app does after its own
 * timeout, 504 in plain text.
 */
final readonly class ResilienceGatewayPage implements Page
{
    /**
     * @param ResilienceWatches $resilienceWatches The watched cases.
     */
    public function __construct(
        private ResilienceWatches $resilienceWatches,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws LogicException When a key is malformed, the page's regions do not fit the theme, or the request carries
     *                        no session.
     *
     * @return PageResponse The case page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->resilienceWatches->gateway($serverRequest);
    }
}
