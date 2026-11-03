<?php

declare(strict_types=1);

namespace App\Reports\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use AIC\Entity\Domain\EntityType;
use App\Reports\Entity\ReportFigure;
use App\Reports\Entity\ReportKind;
use App\Reports\Entity\ReportRange;
use App\Reports\Entity\ReportTables;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

use function substr;

/**
 * Each report's figures for a range (#21): a view with one row per record, the range as its conditions, and AIC's
 * totals to count, add up and average it (AIC #209), so the SQL that defines a figure lives in the module's migration
 * and no date is worked out here. The page, the CSV and the API all read their figures from it, so they agree.
 */
final readonly class FigureRepository
{
    /**
     * Each month's name, January first.
     */
    private const array MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    /**
     * The kinds of lead, by their stored value, with their names.
     */
    private const array KINDS = ['inquiry' => 'Questions', 'test_drive' => 'Test drives'];

    /**
     * @param EntityStore     $entityStore     The entity store.
     * @param RangeRepository $rangeRepository The months a range touches.
     * @param ReportTables    $reportTables    The views' entity types.
     * @param StaffTable      $staffTable      The users' type, staff's, for the salespeople's names.
     */
    public function __construct(
        private EntityStore $entityStore,
        private RangeRepository $rangeRepository,
        private ReportTables $reportTables = new ReportTables(),
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @param ReportKind  $reportKind  A report.
     * @param ReportRange $reportRange Its range.
     *
     * @throws QueryException           When a query fails.
     * @throws InvalidArgumentException When an entity type is malformed.
     *
     * @return list<ReportFigure> Its lines, in the report's order.
     */
    public function figures(ReportKind $reportKind, ReportRange $reportRange): array
    {
        return match ($reportKind) {
            ReportKind::Sales => $this->months($reportRange),
            ReportKind::Salespeople => $this->salespeople($reportRange),
            ReportKind::Inventory => $this->makes($reportRange),
            ReportKind::Leads => $this->leads($reportRange),
        };
    }

    /**
     * @param ReportRange $reportRange A range.
     *
     * @throws QueryException           When a query fails.
     * @throws InvalidArgumentException When an entity type is malformed.
     *
     * @return list<ReportFigure> Every month the range touches, the oldest first, with the sales made in the range and
     *                            their revenue; 0 for none.
     */
    public function months(ReportRange $reportRange): array
    {
        $entityTotals = $this->within($this->reportTables->sales(), 'sold_on', $reportRange)->totals();
        $sales = $entityTotals->countBy('month');
        $revenue = $entityTotals->sumBy('price', 'month');
        $figures = [];

        foreach ($this->rangeRepository->months($reportRange) as $month) {
            $label = (self::MONTHS[(int) substr($month, 5, 2) - 1] ?? '') . ' ' . substr($month, 0, 4);
            $figures[] = new ReportFigure($month, $label, $sales[$month] ?? 0, (int) ($revenue[$month] ?? '0'));
        }

        return $figures;
    }

    /**
     * @param ReportRange $reportRange A range.
     *
     * @throws QueryException           When a query fails.
     * @throws InvalidArgumentException When an entity type is malformed.
     *
     * @return list<ReportFigure> Each salesperson who sold in the range, by login, with their units and revenue, in
     *                            `sumBy()`'s order: the most revenue first, then by login.
     */
    public function salespeople(ReportRange $reportRange): array
    {
        $entityTotals = $this->within($this->reportTables->sales(), 'sold_on', $reportRange)->totals();
        $units = $entityTotals->countBy('salesperson_email');
        $names = $this->names();
        $figures = [];

        foreach ($entityTotals->sumBy('price', 'salesperson_email') as $email => $revenue) {
            $login = $this->key($email);
            $figures[] = new ReportFigure($login, $names[$login] ?? $login, $units[$login] ?? 0, (int) $revenue);
        }

        return $figures;
    }

    /**
     * @param ReportRange $reportRange A range.
     *
     * @throws QueryException           When a query fails.
     * @throws InvalidArgumentException When an entity type is malformed.
     *
     * @return list<ReportFigure> Each make with vehicles on the lot at some time in the range (come by its last day, not
     *                            gone before its first), with how many and their average days on the lot, in
     *                            `countBy()`'s order: the most first, then by make.
     */
    public function makes(ReportRange $reportRange): array
    {
        $entityTotals = $this->entityStore->query($this->reportTables->stock())
            ->where('added_on', $reportRange->until, Comparison::LessOrEqual)
            ->where('left_on', $reportRange->from, Comparison::GreaterOrEqual)
            ->totals();
        $days = $entityTotals->averageBy('days', 'make');
        $figures = [];

        foreach ($entityTotals->countBy('make') as $name => $vehicles) {
            $make = $this->key($name);
            $figures[] = new ReportFigure($make, $make, $vehicles, (int) ($days[$make] ?? '0'));
        }

        return $figures;
    }

    /**
     * @param ReportRange $reportRange A range.
     *
     * @throws QueryException           When a query fails.
     * @throws InvalidArgumentException When an entity type is malformed.
     *
     * @return list<ReportFigure> Questions, then test drives: how many came in during the range, and how many of those
     *                            became a sale; 0 for none.
     */
    public function leads(ReportRange $reportRange): array
    {
        $leads = $this->within($this->reportTables->leads(), 'came_on', $reportRange)->totals()->countBy('kind');
        $sold = $this->within($this->reportTables->leads(), 'came_on', $reportRange)->where('sold', true)->totals()->countBy('kind');
        $figures = [];

        foreach (self::KINDS as $kind => $label) {
            $figures[] = new ReportFigure($kind, $label, $leads[$kind] ?? 0, $sold[$kind] ?? 0);
        }

        return $figures;
    }

    /**
     * @param EntityType  $entityType  A view with a day.
     * @param string      $day         Its day's field.
     * @param ReportRange $reportRange A range.
     *
     * @throws InvalidArgumentException When the view has no such field.
     *
     * @return EntityQuery Its rows whose day is in the range.
     */
    private function within(EntityType $entityType, string $day, ReportRange $reportRange): EntityQuery
    {
        return $this->entityStore->query($entityType)
            ->where($day, $reportRange->from, Comparison::GreaterOrEqual)
            ->where($day, $reportRange->until, Comparison::LessOrEqual);
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return array<string, string> Every member of staff's name, by login.
     */
    private function names(): array
    {
        $names = [];

        foreach ($this->entityStore->query($this->staffTable->type())->rows() as $row) {
            $names[$row->string('email')] = $row->string('name');
        }

        return $names;
    }

    /**
     * A key of the totals' answer as text: PHP turns a key that is a numeric string into an int, so a make named `300`
     * comes back as one.
     *
     * @param int|string $key A key of `countBy()` or `sumBy()`.
     *
     * @return string It as text.
     */
    private function key(int|string $key): string
    {
        return (string) $key;
    }
}
