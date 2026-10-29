<?php

declare(strict_types=1);

namespace App\Leads\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Customers\Entity\CustomerTable;
use App\Inventory\Entity\VehicleTable;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

/**
 * The leads' entity type (#18): `leads`, keyed by `id`, each referring to its vehicle, to its salesperson in `users`
 * (null while unassigned) and to the customer its email matched (null for none).
 */
final readonly class LeadTable
{
    /**
     * @param VehicleTable  $vehicleTable  The vehicles' type, which `vehicle_id` refers to.
     * @param StaffTable    $staffTable    The users' type, which `salesperson_id` refers to.
     * @param CustomerTable $customerTable The customers' type, which `customer_id` refers to.
     */
    public function __construct(
        private VehicleTable $vehicleTable = new VehicleTable(),
        private StaffTable $staffTable = new StaffTable(),
        private CustomerTable $customerTable = new CustomerTable(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The leads.
     */
    public function type(): EntityType
    {
        return new EntityType('leads', 'id', [
            new Field('vehicle_id', FieldType::Reference, false, $this->vehicleTable->type()),
            new Field('kind', FieldType::Text),
            new Field('name', FieldType::Text),
            new Field('email', FieldType::Text),
            new Field('phone', FieldType::Text),
            new Field('message', FieldType::Text),
            new Field('drive_on', FieldType::Date, true),
            new Field('drive_at', FieldType::Text, true),
            new Field('salesperson_id', FieldType::Reference, true, $this->staffTable->type()),
            new Field('customer_id', FieldType::Reference, true, $this->customerTable->type()),
            new Field('status', FieldType::Text),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }
}
