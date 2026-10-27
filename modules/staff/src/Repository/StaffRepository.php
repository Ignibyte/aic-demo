<?php

declare(strict_types=1);

namespace App\Staff\Repository;

use AIC\Auth\Contracts\UserCredentials;
use AIC\Auth\Contracts\UserProvider;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use App\Staff\Entity\Role;
use App\Staff\Entity\StaffMember;
use App\Staff\Entity\StaffTable;
use App\Staff\Service\Roles;
use InvalidArgumentException;
use Override;
use UnexpectedValueException;

/**
 * The users table, as the Authenticator reads it: a member of staff signs in with their email, and the session keeps
 * their id. The two writers save on the same email, which the table holds unique.
 */
final readonly class StaffRepository implements UserProvider
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param StaffTable  $staffTable  The `users` entity type.
     * @param Roles       $roles       Reads a stored role.
     */
    public function __construct(
        private EntityStore $entityStore,
        private StaffTable $staffTable = new StaffTable(),
        private Roles $roles = new Roles(),
    ) {
    }

    /**
     * @param string $login The email they sign in with.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return UserCredentials|null Their id and hash; null when no one signs in with it.
     */
    #[Override]
    public function credentials(string $login): ?UserCredentials
    {
        $row = $this->byEmail($login);

        if (!$row instanceof Row) {
            return null;
        }

        return new UserCredentials((string) $row->int('id'), $row->string('password_hash'));
    }

    /**
     * @param string $email The email they sign in with.
     *
     * @throws QueryException           When the query fails.
     * @throws UnexpectedValueException When their stored role is no role.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return StaffMember|null The member; null when no one signs in with it.
     */
    public function member(string $email): ?StaffMember
    {
        $row = $this->byEmail($email);

        if (!$row instanceof Row) {
            return null;
        }

        return new StaffMember($row->string('email'), $row->string('name'), $this->roles->stored($row->string('role')));
    }

    /**
     * Makes an admin named by their login, or gives an existing one only the new password.
     *
     * @param string $login        What they sign in with.
     * @param string $passwordHash The hash of their password.
     *
     * @throws QueryException           When a statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     */
    public function add(string $login, string $passwordHash): void
    {
        $entityType = $this->staffTable->type();
        $values = ['email' => $login, 'name' => $login, 'role' => Role::Admin->value, 'password_hash' => $passwordHash];

        if ($this->entityStore->insertIfAbsent($entityType, $values, 'email') !== null) {
            return;
        }

        $this->entityStore->query($entityType)->where('email', $login)->changes()->update(['password_hash' => $passwordHash]);
    }

    /**
     * Makes the member, or gives an existing one this name, role and password.
     *
     * @param string $email        What they sign in with.
     * @param string $name         Their name.
     * @param Role   $role         What they are.
     * @param string $passwordHash The hash of their password.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     */
    public function save(string $email, string $name, Role $role, string $passwordHash): void
    {
        $values = ['email' => $email, 'name' => $name, 'role' => $role->value, 'password_hash' => $passwordHash];
        $this->entityStore->upsert($this->staffTable->type(), $values, 'email');
    }

    /**
     * @param string $email An email.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return Row|null The member who signs in with it; null when no one does.
     */
    private function byEmail(string $email): ?Row
    {
        return $this->entityStore->query($this->staffTable->type())->where('email', $email)->first();
    }
}
