<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Domain\PageDocument;
use App\Generated\ThemeLayoutName;
use App\Theme\Components\StoreFooter\StoreFooter;
use App\Theme\Components\StoreHeader\StoreHeader;
use App\Theme\Components\StoreHeading\StoreHeading;
use InvalidArgumentException;

/**
 * The public pages' document in the theme's `store` layout, which the controller picks here: the dealership's header
 * in `masthead`, the page's parts in `content` and its footer in `colophon`. The staff blocks place themselves in
 * `sidebar`, `header` and `footer`, which this layout does not have, so none of them shows here, whoever is signed in;
 * and only this class builds the public header, so no staff page shows it.
 */
final readonly class StorePages
{
    /**
     * The dealership's name.
     */
    private const string DEALERSHIP = 'Ridgeway Motors';

    /**
     * @param PageDocuments $pageDocuments The theme's document around the page.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @param string               $title  The page's title, before the app's name in the document's.
     * @param list<KeyedComponent> $parts  The page's parts, each keyed.
     * @param int                  $status 200, or 404 for a car that is not for sale.
     *
     * @throws InvalidArgumentException When the status or a region does not fit the theme, or the page has no title.
     *
     * @return PageDocument The page.
     */
    public function page(string $title, array $parts, int $status): PageDocument
    {
        if ($title === '') {
            throw new InvalidArgumentException('A public page names its title (AIC #187).');
        }

        $regions = new Regions([
            new Region('masthead', [new StoreHeader(self::DEALERSHIP, CarSlugs::PAGES, 'Used cars, checked and fairly priced')]),
            new Region('content', $parts),
            new Region('colophon', [new StoreFooter(self::DEALERSHIP, 'Every car here is on our lot today.')]),
        ]);

        return $this->pageDocuments->page($regions, $status, $title, ThemeLayoutName::Store);
    }

    /**
     * @throws InvalidArgumentException When a region does not fit the theme.
     *
     * @return PageDocument The 404 for a car that is sold, on hold or unknown, in the public look.
     */
    public function missing(): PageDocument
    {
        $lead = 'That car is not for sale. It may have been sold, or the link may be wrong.';
        $storeHeading = new StoreHeading('car-missing', 'Car not found', $lead, CarSlugs::PAGES, 'See every car for sale');

        return $this->page('Car not found', [new KeyedComponent('heading', $storeHeading)], 404);
    }
}
