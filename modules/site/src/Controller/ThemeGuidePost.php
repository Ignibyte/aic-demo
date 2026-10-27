<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Form\Application\PostedFields;
use App\Site\Form\ThemeEntry;
use App\Site\Service\ThemeSamples;
use App\Theme\Components\TopBar\TopBar;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The style guide's forms, posted to `/theme`. The sample vehicle form is checked for real: what is wrong answers
 * the guide again as 422 with each field's error and the values kept, and a valid one goes back to the guide (303);
 * nothing is stored. The delete and sign-out samples go back to the guide.
 */
final readonly class ThemeGuidePost implements Page
{
    /**
     * @param PageDocuments $pageDocuments The theme's document around the page.
     * @param ThemeSamples  $themeSamples  The guide's content.
     * @param PostedFields  $postedFields  Reads the posted fields.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private ThemeSamples $themeSamples,
        private PostedFields $postedFields,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The post.
     *
     * @throws LogicException When the page's regions do not fit the theme, or the request carries no session.
     *
     * @return PageResponse 303 to `/theme`, or the guide again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        if ($this->postedFields->value($serverRequest, 'intent') !== 'save') {
            return new PageRedirect('/theme');
        }

        $themeEntry = new ThemeEntry(
            $this->postedFields->value($serverRequest, 'name'),
            $this->postedFields->value($serverRequest, 'price'),
            $this->postedFields->value($serverRequest, 'body_style'),
            $this->postedFields->value($serverRequest, 'notes'),
        );
        $problems = $themeEntry->problems();

        if ($problems === []) {
            return new PageRedirect('/theme');
        }

        return $this->pageDocuments->page(new Regions([
            new Region('header', [new TopBar('Theme')]),
            new Region('content', $this->themeSamples->content($serverRequest, $themeEntry, $problems)),
        ]), 422, 'Theme');
    }
}
