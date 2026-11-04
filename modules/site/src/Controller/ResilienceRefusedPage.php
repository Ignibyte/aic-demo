<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Site\Form\ThemeEntry;
use App\Site\Service\ResilienceSamples;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /theme/resilience/refused (#34): the style guide's refused form case, the guide's vehicle form posting here.
 */
final readonly class ResilienceRefusedPage implements Page
{
    /**
     * @param ResilienceSamples $resilienceSamples The unwatched cases.
     */
    public function __construct(
        private ResilienceSamples $resilienceSamples,
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
        return $this->resilienceSamples->refused($serverRequest, new ThemeEntry('', '', '', ''), []);
    }
}
