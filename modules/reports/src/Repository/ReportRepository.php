<?php

declare(strict_types=1);

namespace App\Reports\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use App\Reports\Entity\AgedVehicle;
use App\Reports\Entity\DayFigures;
use App\Reports\Entity\Recipient;
use App\Reports\Entity\ReportTables;
use App\Staff\Entity\Role;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

/**
 * What the reports read (#20), through the entity store: the aged vehicles and the day's figures from the module's two
 * views, and the members of staff of some roles from staff's exported users type.
 */
final readonly class ReportRepository
{
    /**
     * @param EntityStore  $entityStore  The entity store.
     * @param ReportTables $reportTables The views' entity types.
     * @param StaffTable   $staffTable   The users' entity type, staff's.
     */
    public function __construct(
        private EntityStore $entityStore,
        private ReportTables $reportTables = new ReportTables(),
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @param int $days The threshold.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<AgedVehicle> The vehicles not sold on the lot more days than the threshold, the longest first, then by
     *                           VIN.
     */
    public function agedStock(int $days): array
    {
        $entityQuery = $this->entityStore->query($this->reportTables->agedStock())
            ->where('days', $days + 1, Comparison::GreaterOrEqual)
            ->orderByDescending('days')
            ->orderBy('vin');
        $vehicles = [];

        foreach ($entityQuery->rows() as $row) {
            $vehicles[] = $this->agedVehicle($row);
        }

        return $vehicles;
    }

    /**
     * @throws QueryException           When the query fails, or the view gives no row, which it always gives.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return DayFigures The database's today and its figures.
     */
    public function day(): DayFigures
    {
        $row = $this->entityStore->query($this->reportTables->day())->first();

        if (!$row instanceof Row) {
            throw new QueryException('The report_day view gave no row.');
        }

        return new DayFigures(
            $row->string('today'),
            $row->int('sales'),
            $row->int('revenue'),
            $row->int('leads'),
            $row->int('inquiries'),
            $row->int('test_drives'),
            $row->int('new_stock'),
        );
    }

    /**
     * @param list<Role> $roles Roles.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<Recipient> The members of staff who have one of them, by id; a deactivated member is left out (#22).
     */
    public function members(array $roles): array
    {
        $values = [];

        foreach ($roles as $role) {
            $values[] = $role->value;
        }

        $recipients = [];

        $entityQuery = $this->entityStore->query($this->staffTable->type())->whereIn('role', $values)->where('deactivated_on', null);

        foreach ($entityQuery->orderBy('id')->rows() as $row) {
            $recipients[] = $this->recipient($row);
        }

        return $recipients;
    }

    /**
     * Reads the row through a native `Row` parameter: elephc types a row taken straight from a loop over `rows()` too
     * loosely to pass its columns on (as sales' `SaleChoiceRepository` reads its rows).
     *
     * @param Row $row A row of `report_aged_stock`.
     *
     * @throws QueryException When a column is missing.
     *
     * @return AgedVehicle The vehicle.
     */
    private function agedVehicle(Row $row): AgedVehicle
    {
        return new AgedVehicle($row->string('title'), $row->string('vin'), $row->int('days'), $row->int('list_price'));
    }

    /**
     * @param Row $row A row of the users.
     *
     * @throws QueryException When a column is missing.
     *
     * @return Recipient The member of staff.
     */
    private function recipient(Row $row): Recipient
    {
        return new Recipient($row->int('id'), $row->string('email'));
    }
}
