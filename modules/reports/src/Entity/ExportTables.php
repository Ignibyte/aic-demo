<?php

declare(strict_types=1);

namespace App\Reports\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

/**
 * The exports' entity types (#21): `report_exports`, keyed by `id`, each referring to the member who asked; and
 * `report_export_states`, the read-only view that says whether an export is past its seven days.
 */
final readonly class ExportTables
{
    /**
     * @param StaffTable $staffTable The users' type, which `user_id` refers to.
     */
    public function __construct(
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The exports.
     */
    public function exports(): EntityType
    {
        return new EntityType('report_exports', 'id', [
            new Field('user_id', FieldType::Reference, false, $this->staffTable->type()),
            new Field('report', FieldType::Text),
            new Field('from_day', FieldType::Text),
            new Field('to_day', FieldType::Text),
            new Field('status', FieldType::Text),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The `report_export_states` view, read only.
     */
    public function states(): EntityType
    {
        return new EntityType('report_export_states', 'id', [
            new Field('user_id', FieldType::Integer),
            new Field('report', FieldType::Text),
            new Field('from_day', FieldType::Text),
            new Field('to_day', FieldType::Text),
            new Field('status', FieldType::Text),
            new Field('expired', FieldType::Boolean),
        ], true);
    }
}
