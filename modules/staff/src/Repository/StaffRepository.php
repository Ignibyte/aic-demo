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
use RuntimeException;
use UnexpectedValueException;

use function hash;
use function preg_match;
use function substr;

/**
 * The users table, as the Authenticator reads it: a member of staff signs in with their email, and the session keeps
 * their id. The two writers save on the same email, which the table holds unique. A deactivated member, or one added by
 * an admin who has not set a password yet, has no credentials (#22), so the Authenticator checks the decoy hash and
 * answers as for a wrong password. Exported for the settings' password links, which set a member's hash here.
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
     * @return UserCredentials|null Their id and hash; null when no one signs in with it, they are deactivated, or they
     *                              have no password yet.
     */
    #[Override]
    public function credentials(string $login): ?UserCredentials
    {
        $row = $this->byEmail($login);

        if (
            !$row instanceof Row
            || $row->stringOrNull('deactivated_on') !== null
            || $row->string('password_hash') === ''
        ) {
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

        return $this->staffMember($row);
    }

    /**
     * @param string $userId The id AIC's Authenticator keeps in a signed-in session.
     *
     * @throws QueryException           When the query fails.
     * @throws UnexpectedValueException When their stored role is no role.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return StaffMember|null The member with that id while they may sign in; null when no member has it or they
     *                          are deactivated.
     */
    public function standing(string $userId): ?StaffMember
    {
        if (preg_match('/^[1-9]\\d{0,17}$/', $userId) !== 1) {
            return null;
        }

        $row = $this->entityStore->query($this->staffTable->type())->where('id', (int) $userId)->first();

        if (!$row instanceof Row || $row->stringOrNull('deactivated_on') !== null) {
            return null;
        }

        return $this->staffMember($row);
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
     * @param int    $id           The member's id.
     * @param string $passwordHash The hash of the password they chose.
     *
     * @throws RuntimeException         When the statement fails, or no member has the id.
     * @throws InvalidArgumentException When a value does not fit its field.
     */
    public function setPasswordHash(int $id, string $passwordHash): void
    {
        $this->entityStore->update($this->staffTable->type(), $id, ['password_hash' => $passwordHash]);
    }

    /**
     * Deletes the member; the database refuses it while a record names them.
     *
     * @param int $id The member's id.
     *
     * @throws RuntimeException         When the statement fails, a record naming them included, or no member has the id.
     * @throws InvalidArgumentException When the entity type is malformed.
     */
    public function remove(int $id): void
    {
        $this->entityStore->delete($this->staffTable->type(), $id);
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

    /**
     * @param Row $row A `users` row.
     *
     * @throws QueryException           When a column is missing or not of its type.
     * @throws UnexpectedValueException When the stored role is no role.
     *
     * @return StaffMember The member, with the stamp of their stored password hash (#23): a session keeps it at
     *                     sign-in, and a new password, a new hash, no longer matches it.
     */
    private function staffMember(Row $row): StaffMember
    {
        $stamp = substr(hash('sha256', $row->string('password_hash')), 0, 16);

        return new StaffMember($row->string('email'), $row->string('name'), $this->roles->stored($row->string('role')), $stamp);
    }
}
