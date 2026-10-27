<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\SourcedComponent;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use LogicException;
use Override;

/**
 * One of the dashboard's charts as a source (#14, AIC #192), by its key: it reads its figures only in `build()`, which
 * the render pass calls when it renders that chart, so a Tessera request for one chart reads its figures alone. Each
 * chart is anchored `dashboard-<key>` (AIC #188), so a request names it without a path.
 */
final readonly class DashboardChart implements ComponentSource
{
    /**
     * Sales by month.
     */
    public const string SALES = 'sales';

    /**
     * Inventory by make.
     */
    public const string MAKES = 'makes';

    /**
     * Revenue by salesperson.
     */
    public const string SALESPEOPLE = 'salespeople';

    /**
     * What each chart's anchor starts with.
     */
    private const string ANCHOR = 'dashboard-';

    /**
     * @param DashboardCharts $dashboardCharts The charts' panels.
     * @param string          $key             Which chart: `SALES`, `MAKES` or `SALESPEOPLE`.
     */
    public function __construct(
        private DashboardCharts $dashboardCharts,
        private string $key,
    ) {
    }

    /**
     * @throws InvalidArgumentException When the key is malformed.
     *
     * @return KeyedComponent The chart under its key and anchor, built when it is rendered.
     */
    public function keyed(): KeyedComponent
    {
        return new KeyedComponent($this->key, new SourcedComponent($this), '', self::ANCHOR . $this->key);
    }

    /**
     * @throws QueryException When the figures cannot be read.
     * @throws LogicException When a panel's key is malformed, or the key names no chart.
     *
     * @return Component The chart's panel.
     */
    #[Override]
    public function build(): Component
    {
        if ($this->key === self::SALES) {
            return $this->dashboardCharts->sales();
        }

        if ($this->key === self::MAKES) {
            return $this->dashboardCharts->makes();
        }

        if ($this->key === self::SALESPEOPLE) {
            return $this->dashboardCharts->salespeople();
        }

        throw new LogicException('No dashboard chart is keyed "' . $this->key . '".');
    }
}
