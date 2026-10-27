<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Entity\TileFigures;
use App\Theme\Components\StatTiles\StatTile;
use App\Theme\Components\StatTiles\StatTiles;

use function number_format;

/**
 * The dashboard's four figures as the theme's stat tiles: the stock, this month's sales and revenue each beside last
 * month's, and the average days on the lot.
 */
final readonly class DashboardTiles
{
    /**
     * The tiles' key in the dashboard's grid.
     */
    public const string KEY = 'tiles';

    /**
     * @param DashboardWords $dashboardWords Writes counts and money.
     */
    public function __construct(
        private DashboardWords $dashboardWords = new DashboardWords(),
    ) {
    }

    /**
     * @param TileFigures $tileFigures The figures.
     *
     * @return StatTiles This month on the lot.
     */
    public function tiles(TileFigures $tileFigures): StatTiles
    {
        $onHold = number_format($tileFigures->onHold) . ' more on hold';
        $lastSales = number_format($tileFigures->lastSales) . ' last month';
        $revenue = $this->dashboardWords->money($tileFigures->revenue);
        $lastRevenue = $this->dashboardWords->money($tileFigures->lastRevenue) . ' last month';
        $days = $this->dashboardWords->count($tileFigures->days, 'day', 'days');
        $notSold = 'over the ' . $this->dashboardWords->count($tileFigures->notSold, 'vehicle', 'vehicles') . ' not sold';

        return new StatTiles('dashboard-tiles', 'This month on the lot', [
            new StatTile('tile-in-stock', 'Vehicles in stock', number_format($tileFigures->inStock), $onHold),
            new StatTile('tile-sales', 'Sales this month', number_format($tileFigures->sales), $lastSales),
            new StatTile('tile-revenue', 'Revenue this month', $revenue, $lastRevenue),
            new StatTile('tile-days', 'Average days on the lot', $days, $notSold),
        ]);
    }
}
