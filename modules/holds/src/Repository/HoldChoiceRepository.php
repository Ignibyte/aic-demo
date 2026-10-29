<?php

declare(strict_types=1);

namespace App\Holds\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use App\Customers\Entity\CustomerTable;
use App\Holds\Entity\HoldChoice;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Entity\VehicleTable;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

use function trim;

/**
 * What the hold form chooses from and names (#20), read through the entity types the other modules export: the
 * vehicles in stock and the customers as choices, keyed by VIN and by email; a vehicle, a customer and a member of
 * staff found by that key.
 */
final readonly class HoldChoiceRepository
{
    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param VehicleTable  $vehicleTable  The vehicles' entity type, inventory's.
     * @param CustomerTable $customerTable The customers' entity type, customers'.
     * @param StaffTable    $staffTable    The users' entity type, staff's.
     */
    public function __construct(
        private EntityStore $entityStore,
        private VehicleTable $vehicleTable = new VehicleTable(),
        private CustomerTable $customerTable = new CustomerTable(),
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<HoldChoice> The vehicles in stock by VIN, ordered by make, model, the newest model year first, then
     *                          VIN; each labelled `{VIN}: 2023 Toyota Camry SE`, so the form reads VIN first.
     */
    public function vehicles(): array
    {
        $rows = $this->entityStore->query($this->vehicleTable->type())
            ->where('status', VehicleStatus::InStock->value)
            ->orderBy('make')
            ->orderBy('model')
            ->orderByDescending('year')
            ->orderBy('vin')
            ->rows();
        $choices = [];

        foreach ($rows as $row) {
            $choices[] = $this->vehicleChoice($row);
        }

        return $choices;
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<HoldChoice> The customers by name, then id, by email; each labelled `{name} ({email})`.
     */
    public function customers(): array
    {
        $choices = [];

        foreach ($this->entityStore->query($this->customerTable->type())->orderBy('name')->orderBy('id')->rows() as $row) {
            $choices[] = $this->customerChoice($row);
        }

        return $choices;
    }

    /**
     * @param string $vin A VIN, in capitals.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return string The vehicle's status; empty when no vehicle has it.
     */
    public function vehicleStatus(string $vin): string
    {
        $row = $this->entityStore->query($this->vehicleTable->type())->where('vin', $vin)->first();

        return $row instanceof Row
            ? $row->string('status')
            : '';
    }

    /**
     * @param string $vin A VIN, in capitals.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int The vehicle's id; 0 when no vehicle has it.
     */
    public function vehicleId(string $vin): int
    {
        return $this->idWhere($this->vehicleTable->type(), 'vin', $vin);
    }

    /**
     * @param string $email A customer's email.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int Their id; 0 when no customer has it.
     */
    public function customerId(string $email): int
    {
        return $this->idWhere($this->customerTable->type(), 'email', $email);
    }

    /**
     * @param string $login A member of staff's login.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int Their id; 0 when no member of staff has it.
     */
    public function memberId(string $login): int
    {
        return $this->idWhere($this->staffTable->type(), 'email', $login);
    }

    /**
     * @param EntityType $entityType An entity type.
     * @param string     $field      One of its unique fields.
     * @param string     $value      A value of it.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the field is not the type's.
     *
     * @return int The id of the one row that has it; 0 when none has it.
     */
    private function idWhere(EntityType $entityType, string $field, string $value): int
    {
        foreach ($this->entityStore->query($entityType)->where($field, $value)->limit(1)->rows() as $row) {
            return $row->int('id');
        }

        return 0;
    }

    /**
     * Reads the row through a native `Row` parameter, as sales' `SaleChoiceRepository` does: elephc types a row taken
     * straight from a loop over `rows()` too loosely to pass its columns on; so does the one below.
     *
     * @param Row $row A row of the customers.
     *
     * @throws QueryException When a column is missing.
     *
     * @return HoldChoice The customer as a choice.
     */
    private function customerChoice(Row $row): HoldChoice
    {
        return new HoldChoice($row->string('email'), $row->string('name') . ' (' . $row->string('email') . ')');
    }

    /**
     * @param Row $row A row of the vehicles.
     *
     * @throws QueryException When a column is missing.
     *
     * @return HoldChoice The vehicle as a choice.
     */
    private function vehicleChoice(Row $row): HoldChoice
    {
        $vin = $row->string('vin');
        $title = trim($row->int('year') . ' ' . $row->string('make') . ' ' . $row->string('model') . ' ' . $row->string('trim'));

        return new HoldChoice($vin, $vin . ': ' . $title);
    }
}
