<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Dashboard\Repository\DashboardRepository;
use App\Theme\Components\LiveParts\LiveParts;
use InvalidArgumentException;

/**
 * The dashboard's live part (#12): the stat tiles and the latest sales in the theme's `LiveParts`, which Tessera refreshes
 * from the dashboard every five seconds. AIC answers each refresh of `content/c0/PanelGrid.1:live` with this part alone.
 */
final readonly class DashboardLive
{
    /**
     * The part's key in the dashboard's grid; its Tessera path is `content/c0/PanelGrid.1:live`, as the render pass keys
     * it, and changes if the page moves the part.
     */
    public const string KEY = 'live';

    /**
     * The path that answers the refresh: the dashboard's own.
     */
    private const string PATH = '/app';

    /**
     * @param DashboardRepository $dashboardRepository The figures and the latest sales.
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
     * @return LiveParts The tiles, keyed `tiles`, then the latest sales' panel, keyed `feed`.
     */
    public function parts(): LiveParts
    {
        $latest = $this->dashboardRepository->latest(DashboardFeed::LIMIT);

        return new LiveParts(self::PATH, [
            new KeyedComponent(DashboardTiles::KEY, $this->dashboardTiles->tiles($this->dashboardRepository->tiles())),
            new KeyedComponent(DashboardFeed::KEY, $this->dashboardFeed->panel($latest)),
        ]);
    }
}
