<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use App\Site\Service\ResilienceCases;
use App\Site\Service\ResilienceOutage;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /theme/resilience/feed (#34), the style guide's failing poll: "Take the feed down" starts an outage in the
 * visitor's session, so `ResilienceFaults` answers the feed's next three polls 503, and goes back to the case page
 * (303).
 */
final readonly class ResilienceFeedPost implements Page
{
    /**
     * @param ResilienceOutage $resilienceOutage The feed's outage.
     */
    public function __construct(
        private ResilienceOutage $resilienceOutage = new ResilienceOutage(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The take-down post, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return PageResponse 303 to the case page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $this->resilienceOutage->start($serverRequest);

        return new PageRedirect(ResilienceCases::FEED);
    }
}
