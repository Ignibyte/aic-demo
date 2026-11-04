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
use App\Settings\Entity\DealershipProfile;
use App\Settings\Service\DealershipProfiles;
use App\Theme\Components\CompareDock\CompareDock;
use App\Theme\Components\CompareDrawer\CompareDrawer;
use App\Theme\Components\StoreFooter\StoreFooter;
use App\Theme\Components\StoreHeader\StoreHeader;
use App\Theme\Components\StoreHeading\StoreHeading;
use InvalidArgumentException;

/**
 * The public pages' document in the theme's `store` layout, which the controller picks here: the dealership's header
 * in `masthead`, the page's parts in `content` and its footer in `colophon`. The header's name and the footer's
 * address, phone and hours are the settings' profile (#22), read on each page, so a save shows on the next; a page that
 * needs more of the profile (the car's, for its calculator's APR, #26) reads it here first and hands it to
 * `framed()`. The staff blocks place themselves in `sidebar`, `header` and `footer`, which this layout does not have,
 * so none of them shows here, whoever is signed in; and only this class builds the public header, so no staff page
 * shows it. After the footer, `colophon` holds the chat's dock (#32), which each page has chat's exported `ChatDesk`
 * build and hands in: the visitor's open chat on every store page, else the start form on a car's page, else nothing;
 * so it renders at `colophon/chat` on every page, which a Tessera visit keeps as the same node. Last in `colophon`
 * comes the comparison's dock (#33), at `colophon/compare` on every page: the compare bar and its drawer, keyed
 * `drawer` and anchored `compare-drawer`, empty unless the page hands one in (the compare module's Tessera answer).
 */
final readonly class StorePages
{
    /**
     * The footer's first line, before the dealership's address, phone and hours.
     */
    private const string LOT = 'Every car here is on our lot today.';

    /**
     * The list of cars for sale, which the header and the 404 link to: the same path as `CarSlugs::PAGES`, which the
     * frame does not name, so it keeps within PHPMD's coupling limit once it places the comparison's dock (#33).
     */
    private const string CARS = '/cars';

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
     * @throws QueryException           When the profile cannot be read.
     * @throws InvalidArgumentException When its entity type is malformed.
     *
     * @return DealershipProfile The dealership's profile, for a page that shows more of it than the header and footer
     *                           do; that page hands it to `framed()`, so the profile is read once.
     */
    public function profile(): DealershipProfile
    {
        return $this->dealershipProfiles->profile();
    }

    /**
     * @param string               $title          The page's title, before the app's name in the document's.
     * @param list<KeyedComponent> $parts          The page's parts, each keyed.
     * @param int                  $status         200, or 404 for a car that is not for sale.
     * @param KeyedComponent|null  $keyedComponent The chat's dock (#32), which chat's `ChatDesk` builds; null for none.
     *
     * @throws InvalidArgumentException When the status or a region does not fit the theme, or the page has no title.
     * @throws QueryException           When the profile cannot be read.
     *
     * @return PageDocument The page.
     */
    public function page(string $title, array $parts, int $status, ?KeyedComponent $keyedComponent): PageDocument
    {
        return $this->framed($this->dealershipProfiles->profile(), $title, $parts, $status, $keyedComponent);
    }

    /**
     * Frames a page around a profile the caller already read. It sits beside `page()` rather than giving `page()` a
     * nullable profile, because natively (elephc) a `??=` on a nullable parameter does not narrow it, and gate:21
     * refused the `string|null` that reached `StoreHeader`.
     *
     * @param DealershipProfile    $dealershipProfile The profile the page already read, for its header and footer.
     * @param string               $title             The page's title, before the app's name in the document's.
     * @param list<KeyedComponent> $parts             The page's parts, each keyed.
     * @param int                  $status            200, or 422 after a refused lead form.
     * @param KeyedComponent|null  $keyedComponent    The chat's dock, after the footer; null for none.
     * @param CompareDrawer|null   $compareDrawer     The comparison's drawer, filled (#33); null for the empty one every
     *                                                other page carries.
     *
     * @throws InvalidArgumentException When the status or a region does not fit the theme, or the page has no title.
     *
     * @return PageDocument The page.
     */
    public function framed(
        DealershipProfile $dealershipProfile,
        string $title,
        array $parts,
        int $status,
        ?KeyedComponent $keyedComponent,
        ?CompareDrawer $compareDrawer = null,
    ): PageDocument {
        if ($title === '') {
            throw new InvalidArgumentException('A public page names its title (AIC #187).');
        }

        $storeFooter = new StoreFooter($dealershipProfile->name, self::LOT . ' ' . $dealershipProfile->facts() . '.');
        $colophon = $keyedComponent instanceof KeyedComponent
            ? [$storeFooter, $keyedComponent]
            : [$storeFooter];
        $colophon[] = $this->compareDock($compareDrawer);
        $regions = new Regions([
            new Region('masthead', [new StoreHeader($dealershipProfile->name, self::CARS, 'Used cars, checked and fairly priced')]),
            new Region('content', $parts),
            new Region('colophon', $colophon),
        ]);

        return $this->pageDocuments->page($regions, $status, $title, ThemeLayoutName::Store);
    }

    /**
     * @param KeyedComponent|null $keyedComponent The chat's dock; null for none.
     *
     * @throws InvalidArgumentException When a region does not fit the theme.
     * @throws QueryException           When the profile cannot be read.
     *
     * @return PageDocument The 404 for a car that is sold, on hold or unknown, in the public look.
     */
    public function missing(?KeyedComponent $keyedComponent): PageDocument
    {
        $lead = 'That car is not for sale. It may have been sold, or the link may be wrong.';
        $storeHeading = new StoreHeading('car-missing', 'Car not found', $lead, self::CARS, 'See every car for sale');

        return $this->page('Car not found', [new KeyedComponent('heading', $storeHeading)], 404, $keyedComponent);
    }

    /**
     * @param CompareDrawer|null $compareDrawer The drawer the page fills; null for an empty one.
     *
     * @throws InvalidArgumentException When a key or an anchor is malformed.
     *
     * @return KeyedComponent The comparison's dock, keyed `compare`, its drawer keyed `drawer` and anchored
     *                        `compare-drawer`, which the bar's Compare link targets.
     */
    private function compareDock(?CompareDrawer $compareDrawer): KeyedComponent
    {
        $drawer = $compareDrawer instanceof CompareDrawer
            ? $compareDrawer
            : new CompareDrawer('', []);

        return new KeyedComponent('compare', new CompareDock(new KeyedComponent('drawer', $drawer, '', 'compare-drawer')));
    }
}
