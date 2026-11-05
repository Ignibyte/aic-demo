<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Site\Service\CoverageCatalog;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use App\Theme\Components\TopBar\TopBar;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * The Coverage page at `/theme/coverage` (#38): every surface of AIC and Tessera that `bin/aic code:coverage` lists,
 * each with the page where Lotline uses it and the browser test that holds it, or why Lotline does not use it. The
 * style guide links to it.
 */
final readonly class CoveragePage implements Page
{
    /**
     * @param PageDocuments   $pageDocuments   The theme's document around the page.
     * @param CoverageCatalog $coverageCatalog The rows and the page's parts.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private CoverageCatalog $coverageCatalog,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws RuntimeException When `config/coverage.php` does not return a `CoverageRows`.
     * @throws LogicException   When a key is malformed, or the page's regions do not fit the theme.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->pageDocuments->page(
            new Regions([
                new Region('header', [new TopBar('Coverage')]),
                new Region('content', [
                    new Notice(CoverageCatalog::INTRO, NoticeTone::Info, 'coverage-intro'),
                    ...$this->coverageCatalog->content(),
                ]),
            ]),
            200,
            'Coverage',
        );
    }
}
