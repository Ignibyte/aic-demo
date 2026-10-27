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
 * The staff pages' document in the theme's drawer: the top bar's title in `header`, and in `content` any lead parts
 * and then the panels in a grid; the navigation, the account menu and the footer are blocks. The top bar's title titles
 * the document too (`Inventory · Lotline`), in the whole page and in every Tessera fragment, so the tab follows the page.
 */
final readonly class StaffPages
{
    /**
     * The region the lead parts and the grid sit in.
     */
    private const string CONTENT = 'content';

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
     * @param list<KeyedComponent> $lead          Parts set in `content` itself before the grid, each keyed, so each sits
     *                                            at `target()` of its key and may carry a props token; none for most pages.
     *
     * @throws InvalidArgumentException When the status or a region does not fit the theme, or the page has no title.
     *
     * @return PageDocument The page.
     */
    public function page(
        string $title,
        array $panels,
        int $status,
        string $note = '',
        string $documentTitle = '',
        array $lead = [],
    ): PageDocument {
        $pageTitle = $documentTitle === ''
            ? $title
            : $documentTitle;

        if ($pageTitle === '') {
            throw new InvalidArgumentException('A staff page names its title (AIC #187).');
        }

        $regions = new Regions([
            new Region('header', [new TopBar($title, $note)]),
            new Region(self::CONTENT, [...$lead, new PanelGrid($panels)]),
        ]);

        return $this->pageDocuments->page(
            $regions,
            $status,
            $pageTitle,
        );
    }

    /**
     * A lead part's path as Tessera names it: a child of a region carries its own key, so the path is the region's and
     * the key's. Only a region's child carries a props token, which is why a polled part leads; a page that asks
     * whether a refresh of one names it gets the path here, from the class that placed it.
     *
     * @param string $key A lead part's key.
     *
     * @return string Its path, `content/<key>`.
     */
    public function target(string $key): string
    {
        return self::CONTENT . '/' . $key;
    }
}
