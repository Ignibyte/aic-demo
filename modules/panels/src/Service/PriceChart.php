<?php

declare(strict_types=1);

namespace App\Panels\Service;

use App\Inventory\Entity\PricePoint;
use App\Inventory\Entity\Vehicle;
use App\Theme\Components\PriceLine\LinePoint;
use App\Theme\Components\PriceLine\PriceLine;

use function count;
use function implode;
use function intdiv;
use function max;
use function min;
use function number_format;

/**
 * Places a vehicle's list price over time on the price line's drawing (#31): a first point on the day it came, at the
 * first change's old price (its price now when nothing changed), a point at each change, oldest first, and a last
 * point today at its price now. The points are evenly spaced from left to right; the highest price sits at the top
 * and the lowest at the bottom, a flat line in the middle when they are equal.
 */
final readonly class PriceChart
{
    /**
     * The drawing's width.
     */
    private const int WIDTH = 320;

    /**
     * The drawing's height.
     */
    private const int HEIGHT = 120;

    /**
     * The space kept around the line, so a circle on the edge is whole.
     */
    private const int MARGIN = 12;

    /**
     * @param Vehicle          $vehicle The vehicle.
     * @param list<PricePoint> $changes Its list price changes, oldest first.
     * @param string           $today   The database's today, `YYYY-MM-DD`.
     *
     * @return PriceLine The line.
     */
    public function line(Vehicle $vehicle, array $changes, string $today): PriceLine
    {
        $points = $this->points($this->days($vehicle, $changes, $today), $this->prices($vehicle, $changes));
        $pairs = [];

        foreach ($points as $point) {
            $pairs[] = $point->left . ',' . $point->top;
        }

        $empty = $changes === []
            ? 'No price changes recorded.'
            : '';
        $title = 'The list price of the ' . $vehicle->title() . ' over time';

        return new PriceLine('price-line-' . $vehicle->vin, $title, self::WIDTH, self::HEIGHT, implode(' ', $pairs), $points, $empty);
    }

    /**
     * @param Vehicle          $vehicle The vehicle.
     * @param list<PricePoint> $changes Its list price changes, oldest first.
     * @param string           $today   The database's today.
     *
     * @return list<string> Each point's day: the day it came, each change's, then today.
     */
    private function days(Vehicle $vehicle, array $changes, string $today): array
    {
        $days = [$vehicle->lot->addedOn];

        foreach ($changes as $change) {
            $days[] = $change->changedOn;
        }

        $days[] = $today;

        return $days;
    }

    /**
     * @param Vehicle          $vehicle The vehicle.
     * @param list<PricePoint> $changes Its list price changes, oldest first.
     *
     * @return non-empty-list<int> Each point's price: the first change's old price (its price now with none), each
     *                             change's new one, then its price now.
     */
    private function prices(Vehicle $vehicle, array $changes): array
    {
        $first = $changes === []
            ? $vehicle->price->list
            : $changes[0]->oldPrice;
        $prices = [$first];

        foreach ($changes as $change) {
            $prices[] = $change->newPrice;
        }

        $prices[] = $vehicle->price->list;

        return $prices;
    }

    /**
     * @param list<string>        $days   The points' days, oldest first.
     * @param non-empty-list<int> $prices The price on each: at least two, the first and today's.
     *
     * @return list<LinePoint> The points on the drawing.
     */
    private function points(array $days, array $prices): array
    {
        $high = max($prices);
        $low = min($prices);
        $last = count($prices) - 1;
        $wide = self::WIDTH - 2 * self::MARGIN;
        $tall = self::HEIGHT - 2 * self::MARGIN;
        $points = [];

        foreach ($prices as $index => $price) {
            $left = self::MARGIN + intdiv($index * $wide, $last);
            $top = $high === $low
                ? intdiv(self::HEIGHT, 2)
                : self::MARGIN + intdiv(($high - $price) * $tall, $high - $low);
            $points[] = new LinePoint($left, $top, $days[$index] ?? '', '$' . number_format($price));
        }

        return $points;
    }
}
