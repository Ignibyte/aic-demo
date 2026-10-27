<?php

declare(strict_types=1);

namespace App\Staff\Repository;

use AIC\Auth\Contracts\UserCredentials;
use AIC\Auth\Contracts\UserProvider;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Staff\Entity\Role;
use App\Staff\Entity\StaffMember;
use App\Staff\Service\Roles;
use Override;
use UnexpectedValueException;

/**
 * The users table, as the Authenticator reads it: a member of staff signs in with their email, and the session keeps
 * their id. The two writers update on the same email, which the table holds unique.
 */
final readonly class StaffRepository implements UserProvider
{
    /**
     * @param Database $database The database.
     * @param Roles    $roles    Reads a stored role.
     */
    public function __construct(
        private Database $database,
        private Roles $roles = new Roles(),
    ) {
    }

    /**
     * @param string $login The email they sign in with.
     *
     * @throws QueryException When the query fails.
     *
     * @return UserCredentials|null Their id and hash; null when no one signs in with it.
     */
    #[Override]
    public function credentials(string $login): ?UserCredentials
    {
        $row = $this->first($this->database->fetchAll('SELECT id, password_hash FROM users WHERE email = ?', [$login]));

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
     *
     * @return StaffMember|null The member; null when no one signs in with it.
     */
    public function member(string $email): ?StaffMember
    {
        $row = $this->first($this->database->fetchAll('SELECT email, name, role FROM users WHERE email = ?', [$email]));

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
     * @throws QueryException When the statement fails.
     */
    public function add(string $login, string $passwordHash): void
    {
        $this->database->execute(
            'INSERT INTO users (email, name, role, password_hash, created_at) VALUES (?, ?, ?, ?, now())'
            . ' ON CONFLICT (email) DO UPDATE SET password_hash = EXCLUDED.password_hash',
            [$login, $login, Role::Admin->value, $passwordHash],
        );
    }

    /**
     * Makes the member, or gives an existing one this name, role and password.
     *
     * @param string $email        What they sign in with.
     * @param string $name         Their name.
     * @param Role   $role         What they are.
     * @param string $passwordHash The hash of their password.
     *
     * @throws QueryException When the statement fails.
     */
    public function save(string $email, string $name, Role $role, string $passwordHash): void
    {
        $this->database->execute(
            'INSERT INTO users (email, name, role, password_hash, created_at) VALUES (?, ?, ?, ?, now())'
            . ' ON CONFLICT (email) DO UPDATE SET name = EXCLUDED.name, role = EXCLUDED.role,'
            . ' password_hash = EXCLUDED.password_hash',
            [$email, $name, $role->value, $passwordHash],
        );
    }

    /**
     * Reads the first row into a Row-typed value. elephc reads no phpdoc, so to it `$rows[0]` has no type, and it
     * resolved `->string()` there against every class with that method (the schema builder's too) and refused the
     * native build at gate:23; a native `?Row` return gives it the type. No AIC ticket carries this yet.
     *
     * @param list<Row> $rows A query's rows.
     *
     * @return Row|null The first; null when there are none.
     */
    private function first(array $rows): ?Row
    {
        return $rows[0] ?? null;
    }
}
