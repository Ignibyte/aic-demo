<?php

declare(strict_types=1);

namespace App\Reports\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use App\Reports\Entity\RangePreset;
use App\Reports\Entity\ReportRange;
use App\Reports\Entity\ReportTables;
use InvalidArgumentException;

/**
 * The days a report's range is made of (#21), from the views the database works out: the presets, whether a day is one
 * the calendar holds, and the months a range touches.
 */
final readonly class RangeRepository
{
    /**
     * The preset a range defaults to.
     */
    private const string DEFAULT = 'this-month';

    /**
     * @param EntityStore  $entityStore  The entity store.
     * @param ReportTables $reportTables The views' entity types.
     */
    public function __construct(
        private EntityStore $entityStore,
        private ReportTables $reportTables = new ReportTables(),
    ) {
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<RangePreset> This month, last month, this quarter and this year, in that order.
     */
    public function presets(): array
    {
        $presets = [];

        foreach ($this->entityStore->query($this->reportTables->ranges())->orderBy('position')->rows() as $row) {
            $presets[] = $this->preset($row);
        }

        return $presets;
    }

    /**
     * @throws QueryException           When the query fails, or the view has no such row, which it always has.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return ReportRange This month, the range a report shows when none is asked for.
     */
    public function standard(): ReportRange
    {
        $row = $this->entityStore->query($this->reportTables->ranges())->where('name', self::DEFAULT)->first();

        if (!$row instanceof Row) {
            throw new QueryException('The report_ranges view gave no ' . self::DEFAULT . ' row.');
        }

        return $this->preset($row)->reportRange;
    }

    /**
     * @param string $day Text a request gave as a day.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return bool Whether it is a day the calendar holds, `YYYY-MM-DD` from 2000-01-01 to the end of next year.
     */
    public function isDay(string $day): bool
    {
        return $this->entityStore->query($this->reportTables->calendar())->where('day', $day)->first() instanceof Row;
    }

    /**
     * @param ReportRange $reportRange A range.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<string> The months it touches, `YYYY-MM`, the oldest first.
     */
    public function months(ReportRange $reportRange): array
    {
        return $this->entityStore->query($this->reportTables->calendar())
            ->where('day', $reportRange->from, Comparison::GreaterOrEqual)
            ->where('day', $reportRange->until, Comparison::LessOrEqual)
            ->totals()
            ->values('month');
    }

    /**
     * @param Row $row A row of `report_ranges`.
     *
     * @throws QueryException When a column is missing.
     *
     * @return RangePreset The preset.
     */
    private function preset(Row $row): RangePreset
    {
        return new RangePreset($row->string('label'), new ReportRange($row->string('from_day'), $row->string('to_day')));
    }
}
