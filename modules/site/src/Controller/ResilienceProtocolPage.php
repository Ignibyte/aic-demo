<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Site\Service\ResilienceSamples;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /theme/resilience/protocol (#34): the style guide's protocol mismatch. Only a plain request reaches it:
 * `ResilienceFaults` answers every Tessera request to this path 400 with `Tessera-Protocol: 1`, so Tessera loads it
 * whole.
 */
final readonly class ResilienceProtocolPage implements Page
{
    /**
     * @param ResilienceSamples $resilienceSamples The unwatched cases.
     */
    public function __construct(
        private ResilienceSamples $resilienceSamples,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A plain request.
     *
     * @throws LogicException When a key is malformed, or the page's regions do not fit the theme.
     *
     * @return PageResponse The case page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->resilienceSamples->protocol();
    }
}
