<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Domain\PageDocument;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use App\Theme\Components\Panel\Panel;
use App\Theme\Components\PanelGrid\PanelGrid;
use App\Theme\Components\TopBar\TopBar;
use InvalidArgumentException;
use LogicException;

/**
 * The style guide's resilience cases (#34): what Tessera does when something goes wrong, one case a page under
 * `/theme/resilience/`, linked only from the guide's Resilience panel. This class holds the paths, the guide's links
 * and the frame every case page shares: the guide's top bar, a line saying what the case shows, the case in a panel,
 * and a link back to the guide. The cases themselves are `ResilienceSamples`' and `ResilienceWatches`'.
 */
final readonly class ResilienceCases
{
    /**
     * An expired page: a write whose token the session no longer holds.
     */
    public const string EXPIRED = '/theme/resilience/expired';

    /**
     * A write with no answer: the gateway's 504.
     */
    public const string GATEWAY = '/theme/resilience/gateway';

    /**
     * A refused form and its summary.
     */
    public const string REFUSED = '/theme/resilience/refused';

    /**
     * A failing poll.
     */
    public const string FEED = '/theme/resilience/feed';

    /**
     * A protocol mismatch.
     */
    public const string PROTOCOL = '/theme/resilience/protocol';

    /**
     * A full visit.
     */
    public const string FULL_VISIT = '/theme/resilience/full-visit';

    /**
     * The case pages' title and top bar.
     */
    private const string TITLE = 'Resilience';

    /**
     * @param PageDocuments $pageDocuments The theme's document around the page.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return list<KeyedComponent> The guide's Resilience panel: a link to each case page.
     */
    public function links(): array
    {
        return [
            new KeyedComponent('expired', new Link('An expired page (419)', self::EXPIRED)),
            new KeyedComponent('gateway', new Link('A write with no answer (504)', self::GATEWAY)),
            new KeyedComponent('refused', new Link('A refused form (422)', self::REFUSED)),
            new KeyedComponent('feed', new Link('A failing poll (503)', self::FEED)),
            new KeyedComponent('protocol', new Link('A protocol mismatch (400)', self::PROTOCOL)),
            new KeyedComponent('full-visit', new Link('A full visit', self::FULL_VISIT)),
        ];
    }

    /**
     * @param string               $id          The case's id stem, also its panel's key.
     * @param string               $title       Its panel's heading.
     * @param string               $description The line under the heading.
     * @param string               $line        What the case shows and how to trigger it.
     * @param list<KeyedComponent> $body        The case.
     * @param int                  $status      200, or 422 for the refused form.
     *
     * @throws LogicException When a key is malformed, or the page's regions do not fit the theme.
     *
     * @return PageDocument The case page.
     */
    public function page(
        string $id,
        string $title,
        string $description,
        string $line,
        array $body,
        int $status,
    ): PageDocument {
        $back = new KeyedComponent('back', new Link('Back to the style guide', '/theme'));
        $panel = new KeyedComponent($id, new Panel($id, $title, $description, true, [...$body, $back]));

        return $this->pageDocuments->page(new Regions([
            new Region('header', [new TopBar(self::TITLE)]),
            new Region('content', [new Notice($line, NoticeTone::Info, 'resilience-intro'), new PanelGrid([$panel])]),
        ]), $status, self::TITLE);
    }
}
