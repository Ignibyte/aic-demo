<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use App\Site\Service\ResilienceCases;
use App\Site\Service\ResilienceSamples;
use App\Site\Service\ThemeSampleForms;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /theme/resilience/refused (#34), the style guide's refused form case: the guide's vehicle form, checked as
 * `/theme` checks it. What is wrong answers the case page again as 422 with each field's error and the summary above
 * them; a valid form goes back to the case page (303). Nothing is stored.
 */
final readonly class ResilienceRefusedPost implements Page
{
    /**
     * @param ResilienceSamples $resilienceSamples The unwatched cases.
     * @param ThemeSampleForms  $themeSampleForms  Reads the posted vehicle form.
     */
    public function __construct(
        private ResilienceSamples $resilienceSamples,
        private ThemeSampleForms $themeSampleForms = new ThemeSampleForms(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The post.
     *
     * @throws LogicException When a key is malformed, the page's regions do not fit the theme, or the request carries
     *                        no session.
     *
     * @return PageResponse 303 to the case page, or the case page again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $themeEntry = $this->themeSampleForms->posted($serverRequest);
        $problems = $themeEntry->problems();

        if ($problems === []) {
            return new PageRedirect(ResilienceCases::REFUSED);
        }

        return $this->resilienceSamples->refused($serverRequest, $themeEntry, $problems);
    }
}
