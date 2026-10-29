<?php

declare(strict_types=1);

namespace App\Staff\Entity;

/**
 * One member of staff as the settings read them (#22): their id, login, name, role and whether they were deactivated.
 */
final readonly class StaffAccount
{
    /**
     * @param int    $id            Their `users.id`.
     * @param string $email         The email they sign in with.
     * @param string $name          Their name.
     * @param Role   $role          What they are.
     * @param string $deactivatedOn The day they were deactivated, `YYYY-MM-DD`; empty while they are active.
     */
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        public Role $role,
        public string $deactivatedOn,
    ) {
    }

    /**
     * @return bool Whether they may sign in: not deactivated.
     */
    public function active(): bool
    {
        return $this->deactivatedOn === '';
    }

    /**
     * @return bool Whether they are an admin who may sign in, one of those the app must keep at least one of.
     */
    public function activeAdmin(): bool
    {
        return $this->active() && $this->role === Role::Admin;
    }
}
