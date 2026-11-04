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
 * GET /theme/resilience/expired (#34): the style guide's expired page case, a form that expires the session's token
 * and one that sends with the token the page still holds.
 */
final readonly class ResilienceExpiredPage implements Page
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
        return $this->resilienceWatches->expired($serverRequest);
    }
}
