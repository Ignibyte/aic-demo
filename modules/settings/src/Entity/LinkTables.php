<?php

declare(strict_types=1);

namespace App\Settings\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

/**
 * The password links' table and their read-only view (#22), which this module's migrations create: `password_links`,
 * whose member is a reference to staff's users type, and `password_link_states`, which says whether a link's 24 hours
 * have passed.
 */
final readonly class LinkTables
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
     * @return EntityType The links.
     */
    public function links(): EntityType
    {
        return new EntityType('password_links', 'id', [
            new Field('user_id', FieldType::Reference, false, $this->staffTable->type()),
            new Field('used', FieldType::Boolean),
            new Field('created_at', FieldType::CreatedAt),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The `password_link_states` view, read only.
     */
    public function states(): EntityType
    {
        return new EntityType('password_link_states', 'id', [
            new Field('user_id', FieldType::Integer),
            new Field('used', FieldType::Boolean),
            new Field('expired', FieldType::Boolean),
        ], true);
    }
}
