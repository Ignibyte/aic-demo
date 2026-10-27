<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Dashboard\Repository\DashboardRepository;
use App\Theme\Components\LiveParts\LiveParts;
use InvalidArgumentException;
use Override;

/**
 * The dashboard's live part (#12) as a source (#14, AIC #192): the stat tiles and the latest sales in the theme's
 * `LiveParts`, which Tessera refreshes from the dashboard every five seconds. It reads them only in `build()`, which
 * the render pass calls when it renders this part, so a refresh of it reads none of the charts' figures. The poll sits
 * on the part's own root, so each refresh names the part alone; `version()` is what its figures are now, which the
 * page seals in the part's props and compares a refresh's props with, answering 204 when they match (AIC #193).
 */
final readonly class DashboardLive implements ComponentSource
{
    /**
     * The part's key: a child of `content` itself, before the grid, since only a region's child carries props.
     */
    public const string KEY = 'live';

    /**
     * The part's anchor (AIC #188), `data-t-anchor="dashboard-live"` on its root, which names it as `@dashboard-live`
     * wherever the page moves it.
     */
    public const string ANCHOR = 'dashboard-live';

    /**
     * The path that answers the refresh: the dashboard's own.
     */
    private const string PATH = '/app';

    /**
     * @param DashboardRepository $dashboardRepository The figures, the latest sales and their version.
     * @param DashboardTiles      $dashboardTiles      The stat tiles.
     * @param DashboardFeed       $dashboardFeed       The latest sales' panel.
     */
    public function __construct(
        private DashboardRepository $dashboardRepository,
        private DashboardTiles $dashboardTiles = new DashboardTiles(),
        private DashboardFeed $dashboardFeed = new DashboardFeed(),
    ) {
    }

    /**
     * @throws QueryException           When the figures cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Component The tiles, keyed `tiles`, then the latest sales' panel, keyed `feed`, in a `LiveParts`.
     */
    #[Override]
    public function build(): Component
    {
        $latest = $this->dashboardRepository->latest(DashboardFeed::LIMIT);

        return new LiveParts(self::PATH, [
            new KeyedComponent(DashboardTiles::KEY, $this->dashboardTiles->tiles($this->dashboardRepository->tiles())),
            new KeyedComponent(DashboardFeed::KEY, $this->dashboardFeed->panel($latest)),
        ]);
    }

    /**
     * @throws QueryException           When a count or the day cannot be read.
     * @throws InvalidArgumentException When an entity type is malformed.
     *
     * @return string What the tiles and the latest sales are now (`DashboardRepository::version()`).
     */
    public function version(): string
    {
        return $this->dashboardRepository->version();
    }
}
