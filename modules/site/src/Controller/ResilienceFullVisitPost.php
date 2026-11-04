<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageFullVisit;
use App\Site\Service\ResilienceCases;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /theme/resilience/full-visit (#34), the style guide's full visit case: it answers with a full visit to the case
 * page, so Tessera gets `Tessera-Full-Visit` and hands the address to the browser, and a plain browser gets 303.
 */
final readonly class ResilienceFullVisitPost implements Page
{
    /**
     * @param ServerRequestInterface $serverRequest The post.
     *
     * @return PageResponse The full visit to the case page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return new PageFullVisit(ResilienceCases::FULL_VISIT);
    }
}
