<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Domain\PageDocument;
use AIC\Database\Contracts\QueryException;
use App\Generated\ThemeLayoutName;
use App\Settings\Service\DealershipProfiles;
use App\Theme\Components\StoreFooter\StoreFooter;
use App\Theme\Components\StoreHeader\StoreHeader;
use App\Theme\Components\StoreHeading\StoreHeading;
use InvalidArgumentException;

/**
 * The public pages' document in the theme's `store` layout, which the controller picks here: the dealership's header
 * in `masthead`, the page's parts in `content` and its footer in `colophon`. The header's name and the footer's
 * address, phone and hours are the settings' profile (#22), read on each page, so a save shows on the next. The staff
 * blocks place themselves in
 * `sidebar`, `header` and `footer`, which this layout does not have, so none of them shows here, whoever is signed in;
 * and only this class builds the public header, so no staff page shows it.
 */
final readonly class StorePages
{
    /**
     * The footer's first line, before the dealership's address, phone and hours.
     */
    private const string LOT = 'Every car here is on our lot today.';

    /**
     * @param PageDocuments      $pageDocuments      The theme's document around the page.
     * @param DealershipProfiles $dealershipProfiles The dealership's name, address, phone and hours (#22).
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private DealershipProfiles $dealershipProfiles,
    ) {
    }

    /**
     * @param string               $title  The page's title, before the app's name in the document's.
     * @param list<KeyedComponent> $parts  The page's parts, each keyed.
     * @param int                  $status 200, or 404 for a car that is not for sale.
     *
     * @throws InvalidArgumentException When the status or a region does not fit the theme, or the page has no title.
     * @throws QueryException           When the profile cannot be read.
     *
     * @return PageDocument The page.
     */
    public function page(string $title, array $parts, int $status): PageDocument
    {
        if ($title === '') {
            throw new InvalidArgumentException('A public page names its title (AIC #187).');
        }

        $dealershipProfile = $this->dealershipProfiles->profile();
        $regions = new Regions([
            new Region('masthead', [new StoreHeader($dealershipProfile->name, CarSlugs::PAGES, 'Used cars, checked and fairly priced')]),
            new Region('content', $parts),
            new Region('colophon', [new StoreFooter($dealershipProfile->name, self::LOT . ' ' . $dealershipProfile->facts() . '.')]),
        ]);

        return $this->pageDocuments->page($regions, $status, $title, ThemeLayoutName::Store);
    }

    /**
     * @throws InvalidArgumentException When a region does not fit the theme.
     * @throws QueryException           When the profile cannot be read.
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
