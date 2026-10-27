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
 * `content`; the navigation, the account menu and the footer are blocks. The top bar's title titles the document too
 * (`Inventory · Lotline`), in the whole page and in every Tessera fragment, so the tab follows the page.
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
     * @param string               $title         The top bar's title, and the document's unless $documentTitle names one.
     * @param list<KeyedComponent> $panels        The page's panels, each keyed.
     * @param int                  $status        200, or 422 after a refused form.
     * @param string               $note          A line under the title, such as the dashboard's greeting; empty for none.
     * @param string               $documentTitle The document's title when it is not the top bar's; empty for the top bar's.
     *
     * @throws InvalidArgumentException When the status or a region does not fit the theme.
     *
     * @return PageDocument The page.
     */
    public function page(
        string $title,
        array $panels,
        int $status,
        string $note = '',
        string $documentTitle = '',
    ): PageDocument {
        return $this->pageDocuments->page(
            new Regions([new Region('header', [new TopBar($title, $note)]), new Region('content', [new PanelGrid($panels)])]),
            $status,
            null,
            $documentTitle === '' ? $title : $documentTitle,
        );
    }
}
