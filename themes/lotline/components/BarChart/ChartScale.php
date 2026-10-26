<?php

declare(strict_types=1);

namespace App\Theme\Components\BarChart;

use function intdiv;
use function max;

/**
 * A chart's value axis, from old Lotline: four steps of a round size (1, 2 or 5 times a power of ten) that reach the
 * largest value, and lengths measured against their top.
 */
final readonly class ChartScale
{
    /**
     * How many steps the axis has.
     */
    public const int STEPS = 4;

    /**
     * One step's size.
     */
    private int $step;

    /**
     * @param int $largest The largest value the axis must reach.
     */
    public function __construct(int $largest)
    {
        $wanted = max(1, intdiv(max(1, $largest) + self::STEPS - 1, self::STEPS));
        $power = 1;

        while ($power * 10 <= $wanted) {
            $power *= 10;
        }

        $this->step = $this->round($wanted, $power);
    }

    /**
     * @param int $tick A step, from 0.
     *
     * @return int Its value.
     */
    public function tick(int $tick): int
    {
        return $tick * $this->step;
    }

    /**
     * @param int $value  A value.
     * @param int $length The axis's length.
     *
     * @return int The value's length on it, rounded to the nearest whole.
     */
    public function length(int $value, int $length): int
    {
        $top = $this->step * self::STEPS;

        return intdiv(max(0, $value) * $length * 2 + $top, $top * 2);
    }

    /**
     * @param int $wanted The smallest step that reaches the largest value.
     * @param int $power  The power of ten at or below it.
     *
     * @return int The first of 1, 2 or 5 times the power that is not smaller, else ten times it.
     */
    private function round(int $wanted, int $power): int
    {
        foreach ([$power, $power * 2, $power * 5] as $candidate) {
            if ($candidate >= $wanted) {
                return $candidate;
            }
        }

        return $power * 10;
    }
}
