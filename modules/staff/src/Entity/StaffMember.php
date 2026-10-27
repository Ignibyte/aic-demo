<?php

declare(strict_types=1);

namespace App\Staff\Entity;

/**
 * One member of staff, as the header and `/app` show them.
 */
final readonly class StaffMember
{
    /**
     * @param string $email The email they sign in with.
     * @param string $name  Their name.
     * @param Role   $role  What they are.
     */
    public function __construct(
        public string $email,
        public string $name,
        public Role $role,
    ) {
    }
}
