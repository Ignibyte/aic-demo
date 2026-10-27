<?php

declare(strict_types=1);

namespace App\Staff\Service;

use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Domain\PageDocument;
use App\Theme\Components\PanelGrid\PanelGrid;
use App\Theme\Components\TopBar\TopBar;
use InvalidArgumentException;

/**
 * The staff pages' document in the theme's drawer: the top bar's title in `header` and the panels in a grid in
 * `content`; the navigation, the account menu and the footer are blocks.
 */
final readonly class StaffPages
{
    /**
     * @param PageDocuments $pageDocuments The theme's document around the page.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @param string               $title  The top bar's title.
     * @param list<KeyedComponent> $panels The page's panels, each keyed.
     * @param int                  $status 200, or 422 after a refused form.
     *
     * @throws InvalidArgumentException When the status or a region does not fit the theme.
     *
     * @return PageDocument The page.
     */
    public function page(string $title, array $panels, int $status): PageDocument
    {
        return $this->pageDocuments->page(
            new Regions([new Region('header', [new TopBar($title)]), new Region('content', [new PanelGrid($panels)])]),
            $status,
        );
    }
}
