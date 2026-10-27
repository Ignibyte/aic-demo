<?php

declare(strict_types=1);

namespace App\Staff\Service;

use App\Staff\Entity\Role;
use UnexpectedValueException;

/**
 * Reads a stored role value back as its Role, from the users table or the session. Anything else is refused rather
 * than read as some role.
 */
final readonly class Roles
{
    /**
     * @param string $value A role as `users.role` stores it.
     *
     * @throws UnexpectedValueException When it is no role's value.
     *
     * @return Role The role.
     */
    public function stored(string $value): Role
    {
        foreach ([Role::Admin, Role::SalesManager, Role::Salesperson] as $role) {
            if ($role->value === $value) {
                return $role;
            }
        }

        throw new UnexpectedValueException('No role is stored as "' . $value . '".');
    }
}
