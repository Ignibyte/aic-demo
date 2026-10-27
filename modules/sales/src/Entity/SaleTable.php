<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Customers\Entity\CustomerTable;
use App\Inventory\Entity\VehicleTable;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

/**
 * The `sales` table as an entity type (AIC #185): sales' migration creates it, so this module alone names it. Its
 * vehicle, customer and salesperson are references to the entity types their modules export (AIC #190), so a query
 * joins them with `with()` and no table of theirs is named here.
 */
final readonly class SaleTable
{
    /**
     * @param VehicleTable  $vehicleTable  The `vehicles` entity type, inventory's.
     * @param CustomerTable $customerTable The `customers` entity type, customers'.
     * @param StaffTable    $staffTable    The `users` entity type, staff's.
     */
    public function __construct(
        private VehicleTable $vehicleTable = new VehicleTable(),
        private CustomerTable $customerTable = new CustomerTable(),
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When a field is malformed.
     *
     * @return EntityType The `sales` table: one row per vehicle sold, which it holds unique, with its customer, its
     *                    salesperson, the price in whole dollars, the day and when the row was made.
     */
    public function type(): EntityType
    {
        return new EntityType('sales', 'id', [
            new Field('vehicle_id', FieldType::Reference, false, $this->vehicleTable->type()),
            new Field('customer_id', FieldType::Reference, false, $this->customerTable->type()),
            new Field('salesperson_id', FieldType::Reference, false, $this->staffTable->type()),
            new Field('price', FieldType::Integer),
            new Field('sold_on', FieldType::Date),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }
}
