<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Site\Form\ThemeEntry;
use App\Site\Service\ThemeSamples;
use App\Theme\Components\TopBar\TopBar;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The style guide at `/theme`: every part of Lotline's theme with sample data, in the drawer. Its list answers its
 * `q`, `status`, `sort` and `page` query fields.
 */
final readonly class ThemeGuidePage implements Page
{
    /**
     * @param PageDocuments $pageDocuments The theme's document around the page.
     * @param ThemeSamples  $themeSamples  The guide's content.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private ThemeSamples $themeSamples,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: the list's query and the visitor's token.
     *
     * @throws LogicException When the page's regions do not fit the theme, or the request carries no session.
     *
     * @return PageResponse The guide.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $content = $this->themeSamples->content($serverRequest, new ThemeEntry('', '', '', ''), []);

        return $this->pageDocuments->page(
            new Regions([new Region('header', [new TopBar('Theme')]), new Region('content', $content)]),
            200,
            null,
            'Theme',
        );
    }
}
