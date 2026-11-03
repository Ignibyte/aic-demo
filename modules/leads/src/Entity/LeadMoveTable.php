<?php

declare(strict_types=1);

namespace App\Leads\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

/**
 * The lead moves' entity type (#30): `lead_moves`, keyed by `id`, each referring to its lead and to the member of staff
 * who moved it in `users` (null once that user is removed).
 */
final readonly class LeadMoveTable
{
    /**
     * @param LeadTable  $leadTable  The leads' type, which `lead_id` refers to.
     * @param StaffTable $staffTable The users' type, which `user_id` refers to.
     */
    public function __construct(
        private LeadTable $leadTable = new LeadTable(),
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The lead moves.
     */
    public function type(): EntityType
    {
        return new EntityType('lead_moves', 'id', [
            new Field('lead_id', FieldType::Reference, false, $this->leadTable->type()),
            new Field('from_stage', FieldType::Text),
            new Field('to_stage', FieldType::Text),
            new Field('user_id', FieldType::Reference, true, $this->staffTable->type()),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }
}
