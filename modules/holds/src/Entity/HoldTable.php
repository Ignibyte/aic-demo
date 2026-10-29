<?php

declare(strict_types=1);

namespace App\Holds\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Customers\Entity\CustomerTable;
use App\Inventory\Entity\VehicleTable;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

/**
 * The holds' entity types (#20): `holds`, keyed by `id`, each referring to its vehicle, its customer and the member of
 * staff who placed it (null when they are gone); and `hold_states`, the read-only view of each hold as staff read it.
 */
final readonly class HoldTable
{
    /**
     * @param VehicleTable  $vehicleTable  The vehicles' type, which `vehicle_id` refers to.
     * @param CustomerTable $customerTable The customers' type, which `customer_id` refers to.
     * @param StaffTable    $staffTable    The users' type, which `placed_by` refers to.
     */
    public function __construct(
        private VehicleTable $vehicleTable = new VehicleTable(),
        private CustomerTable $customerTable = new CustomerTable(),
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The holds.
     */
    public function type(): EntityType
    {
        return new EntityType('holds', 'id', [
            new Field('vehicle_id', FieldType::Reference, false, $this->vehicleTable->type()),
            new Field('customer_id', FieldType::Reference, true, $this->customerTable->type()),
            new Field('placed_by', FieldType::Reference, true, $this->staffTable->type()),
            new Field('note', FieldType::Text),
            new Field('status', FieldType::Text),
            new Field('created_at', FieldType::CreatedAt),
            new Field('updated_at', FieldType::UpdatedAt),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The `hold_states` view, read only.
     */
    public function states(): EntityType
    {
        return new EntityType('hold_states', 'id', [
            new Field('vehicle_id', FieldType::Integer),
            new Field('status', FieldType::Text),
            new Field('note', FieldType::Text),
            new Field('title', FieldType::Text),
            new Field('vin', FieldType::Text),
            new Field('customer_name', FieldType::Text),
            new Field('placed_by', FieldType::Integer),
            new Field('placer_name', FieldType::Text),
            new Field('placer_email', FieldType::Text),
            new Field('placed_on', FieldType::Text),
            new Field('expires', FieldType::Text),
            new Field('expired', FieldType::Boolean),
        ], true);
    }
}
