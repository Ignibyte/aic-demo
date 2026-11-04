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
 * GET /theme/resilience/feed (#34): the style guide's failing poll, which its feed also polls every second. While the
 * session's outage has polls left to fail, `ResilienceFaults` answers those polls 503 before they reach this page.
 */
final readonly class ResilienceFeedPage implements Page
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
     * @return PageResponse The case page, or its feed alone for a poll.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->resilienceWatches->feed($serverRequest);
    }
}
