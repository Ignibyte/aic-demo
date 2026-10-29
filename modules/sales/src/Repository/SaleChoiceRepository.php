<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use AIC\Entity\Domain\Condition;
use AIC\Entity\Domain\EntityType;
use App\Customers\Entity\CustomerTable;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Entity\VehicleTable;
use App\Inventory\Repository\TodayRepository;
use App\Sales\Entity\Choice;
use App\Sales\Entity\SaleVehicle;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

use function trim;

/**
 * What a sale form chooses from and names, read through the entity types the other modules export (#14): the vehicles
 * not sold, the customers and the staff as options, each keyed by what is unique in its table (a VIN, an email, a
 * login) and labelled here; a vehicle, a customer and a member of staff found by that key; and the database's today.
 */
final readonly class SaleChoiceRepository
{
    /**
     * @param EntityStore     $entityStore     The entity store.
     * @param TodayRepository $todayRepository The database's today, inventory's.
     * @param VehicleTable    $vehicleTable    The `vehicles` entity type, inventory's.
     * @param CustomerTable   $customerTable   The `customers` entity type, customers'.
     * @param StaffTable      $staffTable      The `users` entity type, staff's.
     */
    public function __construct(
        private EntityStore $entityStore,
        private TodayRepository $todayRepository,
        private VehicleTable $vehicleTable = new VehicleTable(),
        private CustomerTable $customerTable = new CustomerTable(),
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @param string $chosen The VIN a form chose, offered even when it is sold; empty for none.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<Choice> The vehicles a sale may name, by VIN, ordered by make, model and VIN; each labelled with its
     *                      year, make, model and trim, then its VIN.
     */
    public function vehicles(string $chosen): array
    {
        $entityQuery = $this->entityStore->query($this->vehicleTable->type())
            ->whereAny([new Condition('status', VehicleStatus::Sold->value, Comparison::NotEquals), new Condition('vin', $chosen)])
            ->orderBy('make')
            ->orderBy('model')
            ->orderBy('vin');
        $choices = [];

        foreach ($entityQuery->rows() as $row) {
            $choices[] = $this->vehicleChoice($row);
        }

        return $choices;
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<Choice> The customers by name, by email, each labelled with the email that tells two of a name apart.
     */
    public function customers(): array
    {
        $choices = [];

        foreach ($this->byName($this->customerTable->type())->rows() as $row) {
            $choices[] = $this->customerChoice($row);
        }

        return $choices;
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<Choice> Every member of staff by name, by login, deactivated ones too: the list's filter, which
     *                      past sales by any of them still go through.
     */
    public function staff(): array
    {
        return $this->staffChoices($this->byName($this->staffTable->type()));
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<Choice> The staff a new sale may name, by name, by login: those an admin deactivated are left out
     *                      (#22).
     */
    public function sellers(): array
    {
        return $this->staffChoices($this->byName($this->staffTable->type())->where('deactivated_on', null));
    }

    /**
     * @param string $vin A VIN, in capitals.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return SaleVehicle|null The vehicle with it; null when none has it.
     */
    public function vehicle(string $vin): ?SaleVehicle
    {
        $row = $this->entityStore->query($this->vehicleTable->type())->where('vin', $vin)->first();

        return $row instanceof Row
            ? new SaleVehicle(
                $row->int('id'),
                $row->string('status') === VehicleStatus::Sold->value,
                $row->int('list_price'),
                $row->string('added_on'),
            )
            : null;
    }

    /**
     * @param string $email A customer's email.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int The customer's id; 0 when no customer has it.
     */
    public function customerId(string $email): int
    {
        return $this->idByEmail($this->customerTable->type(), $email);
    }

    /**
     * @param string $login A member of staff's login.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int Their id; 0 when no member of staff has it, or the one who does was deactivated (#22).
     */
    public function memberId(string $login): int
    {
        $row = $this->entityStore->query($this->staffTable->type())->where('email', $login)->where('deactivated_on', null)->first();

        return $row instanceof Row
            ? $row->int('id')
            : 0;
    }

    /**
     * @throws QueryException           When the query fails, or the today view gives no row.
     * @throws InvalidArgumentException When its entity type is malformed.
     *
     * @return string The database's today, `YYYY-MM-DD`.
     */
    public function currentDay(): string
    {
        return $this->todayRepository->today();
    }

    /**
     * @param EntityQuery $entityQuery A query over the staff, in order.
     *
     * @throws QueryException When the query fails.
     *
     * @return list<Choice> The staff it finds, as choices.
     */
    private function staffChoices(EntityQuery $entityQuery): array
    {
        $choices = [];

        foreach ($entityQuery->rows() as $row) {
            $choices[] = $this->staffChoice($row);
        }

        return $choices;
    }

    /**
     * @param EntityType $entityType An entity type with a name.
     *
     * @throws InvalidArgumentException When it has no name.
     *
     * @return EntityQuery Its rows by name, then by id.
     */
    private function byName(EntityType $entityType): EntityQuery
    {
        return $this->entityStore->query($entityType)->orderBy('name')->orderBy('id');
    }

    /**
     * @param EntityType $entityType An entity type whose email is unique.
     * @param string     $email      An email.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When it has no email.
     *
     * @return int The id of the row that has it; 0 when none has it.
     */
    private function idByEmail(EntityType $entityType, string $email): int
    {
        $row = $this->entityStore->query($entityType)->where('email', $email)->first();

        return $row instanceof Row
            ? $row->int('id')
            : 0;
    }

    /**
     * Reads the row through a native `Row` parameter, as `SaleRows::sale()` does; so do the two below.
     *
     * @param Row $row A row of the vehicles.
     *
     * @throws QueryException When a column is missing.
     *
     * @return Choice The vehicle as a choice: `2023 Toyota Camry SE, {VIN}`.
     */
    private function vehicleChoice(Row $row): Choice
    {
        $title = trim($row->int('year') . ' ' . $row->string('make') . ' ' . $row->string('model') . ' ' . $row->string('trim'));

        return new Choice($row->string('vin'), $title . ', ' . $row->string('vin'));
    }

    /**
     * @param Row $row A row of the customers.
     *
     * @throws QueryException When a column is missing.
     *
     * @return Choice The customer as a choice: `{name} ({email})`.
     */
    private function customerChoice(Row $row): Choice
    {
        return new Choice($row->string('email'), $row->string('name') . ' (' . $row->string('email') . ')');
    }

    /**
     * @param Row $row A row of the staff.
     *
     * @throws QueryException When a column is missing.
     *
     * @return Choice The member as a choice, labelled with their name.
     */
    private function staffChoice(Row $row): Choice
    {
        return new Choice($row->string('email'), $row->string('name'));
    }
}
