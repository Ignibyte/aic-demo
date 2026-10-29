<?php

declare(strict_types=1);

namespace App\Staff\Service;

use App\Staff\Entity\Role;
use UnexpectedValueException;

/**
 * Reads a stored role value back as its Role, from the users table or the session. Anything else is refused rather
 * than read as some role. Exported (#22): the settings list the roles in a member's form and read a posted one back.
 */
final readonly class Roles
{
    /**
     * @return list<Role> Every role, the most rights first: the order the settings' role select lists them in.
     */
    public function all(): array
    {
        return [Role::Admin, Role::SalesManager, Role::Salesperson];
    }

    /**
     * @param string $value A role as `users.role` stores it.
     *
     * @throws UnexpectedValueException When it is no role's value.
     *
     * @return Role The role.
     */
    public function stored(string $value): Role
    {
        foreach ($this->all() as $role) {
            if ($role->value === $value) {
                return $role;
            }
        }

        throw new UnexpectedValueException('No role is stored as "' . $value . '".');
    }
}
