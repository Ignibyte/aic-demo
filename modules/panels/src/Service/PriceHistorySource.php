<?php

declare(strict_types=1);

namespace App\Panels\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Repository\TodayRepository;
use App\Inventory\Repository\VehiclePriceRepository;
use InvalidArgumentException;
use Override;

/**
 * A vehicle's price line as a source (#31, AIC #192): it holds the price changes' repository, the database's today and
 * the vehicle, and reads only in `build()`, which the render pass calls when it renders the line.
 */
final readonly class PriceHistorySource implements ComponentSource
{
    /**
     * The most changes the line shows, the newest.
     */
    public const int SHOWN = 24;

    /**
     * @param VehiclePriceRepository $vehiclePriceRepository The vehicle's price changes.
     * @param TodayRepository        $todayRepository        The database's today, where the line ends.
     * @param Vehicle                $vehicle                The vehicle.
     * @param PriceChart             $priceChart             Places the points.
     */
    public function __construct(
        private VehiclePriceRepository $vehiclePriceRepository,
        private TodayRepository $todayRepository,
        private Vehicle $vehicle,
        private PriceChart $priceChart = new PriceChart(),
    ) {
    }

    /**
     * @throws QueryException           When the changes or the day cannot be read.
     * @throws InvalidArgumentException When an entity type is malformed.
     *
     * @return Component The line, from the day the vehicle came to today.
     */
    #[Override]
    public function build(): Component
    {
        return $this->priceChart->line(
            $this->vehicle,
            $this->vehiclePriceRepository->history($this->vehicle->vin, self::SHOWN),
            $this->todayRepository->today(),
        );
    }
}
