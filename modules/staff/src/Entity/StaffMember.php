<?php

declare(strict_types=1);

namespace App\Staff\Entity;

/**
 * One member of staff, as the header and `/app` show them, with the stamp of the password they signed in with (#23).
 */
final readonly class StaffMember
{
    /**
     * @param string $email The email they sign in with.
     * @param string $name  Their name.
     * @param Role   $role  What they are.
     * @param string $stamp The first 16 hex digits of the SHA-256 of their stored password hash, which a new password
     *                      changes; empty where it is not read.
     */
    public function __construct(
        public string $email,
        public string $name,
        public Role $role,
        public string $stamp = '',
    ) {
    }
}
