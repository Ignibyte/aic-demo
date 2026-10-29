<?php

declare(strict_types=1);

namespace App\Staff\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use App\Staff\Entity\AccountWrite;
use App\Staff\Entity\Role;
use App\Staff\Entity\StaffAccount;
use App\Staff\Entity\StaffTable;
use App\Staff\Service\Roles;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

use function count;

/**
 * The members of staff as the settings change them (#22), through the entity store; exported, since the `settings`
 * module's pages make the changes and `users` is this module's table. A change and a deactivation each run in one
 * `EntityStore::transaction()` that first reads the active admins `FOR UPDATE`, in id order, then the member's row: two
 * admins demoting each other at once take the locks in one order, so the second waits for the first, sees one admin
 * fewer, and cannot leave none (a deadlock, and its 500, cannot happen). The member is compared with what the form
 * showed, so a save made on a stale copy writes nothing. The closures are static and call the
 * public `…Locked()` methods: natively a closure does not bind `$this` (AIC #184), as holds' repository notes.
 */
final readonly class AccountRepository
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param StaffTable  $staffTable  The `users` entity type and the `staff_today` view.
     * @param Roles       $roles       Reads a stored role.
     */
    public function __construct(
        private EntityStore $entityStore,
        private StaffTable $staffTable = new StaffTable(),
        private Roles $roles = new Roles(),
    ) {
    }

    /**
     * @throws RuntimeException         When the query fails, or a stored role is no role.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<StaffAccount> Every member, by name and then by id.
     */
    public function all(): array
    {
        $accounts = [];

        foreach ($this->entityStore->query($this->staffTable->type())->orderBy('name')->orderBy('id')->rows() as $row) {
            $accounts[] = $this->account($row);
        }

        return $accounts;
    }

    /**
     * @param int $id A `users.id`.
     *
     * @throws RuntimeException         When the query fails, or the stored role is no role.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return StaffAccount|null The member; null when no one has that id.
     */
    public function find(int $id): ?StaffAccount
    {
        return $this->first($this->entityStore->query($this->staffTable->type())->where('id', $id));
    }

    /**
     * @param string $email The email someone signs in with.
     *
     * @throws RuntimeException         When the query fails, or the stored role is no role.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return StaffAccount|null The member who signs in with it; null when no one does.
     */
    public function byLogin(string $email): ?StaffAccount
    {
        return $this->first($this->entityStore->query($this->staffTable->type())->where('email', $email));
    }

    /**
     * Adds a member with no password: until a link sets one, they have no credentials.
     *
     * @param string $email Their login, checked.
     * @param string $name  Their name, checked.
     * @param string $role  What they are, as `users.role` stores it.
     *
     * @throws RuntimeException         When the statement fails, or the role is no role.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return int The new member's id; 0 when someone already signs in with the email, which no member's id is.
     */
    public function add(string $email, string $name, string $role): int
    {
        $values = ['email' => $email, 'name' => $name, 'role' => $this->roles->stored($role)->value, 'password_hash' => ''];

        // 0 rather than null: elephc does not narrow a nullable id for the caller (no AIC ticket carries it yet).
        return $this->entityStore->insertIfAbsent($this->staffTable->type(), $values, 'email') ?? 0;
    }

    /**
     * Gives the member a new name and role, unless the stored ones are no longer those the form showed, or the change
     * would leave no active admin.
     *
     * @param int    $id       The member's id.
     * @param string $seenName The name the form showed.
     * @param string $seenRole The role the form showed, as `users.role` stores it.
     * @param string $name     The new name, checked.
     * @param string $role     The new role, as `users.role` stores it.
     *
     * @throws Throwable When a statement fails, or the new role is no role; the transaction is rolled back first.
     *
     * @return string An `AccountWrite` outcome: saved, stale, last-admin or missing.
     */
    public function change(int $id, string $seenName, string $seenRole, string $name, string $role): string
    {
        $accountRepository = $this;
        $accountWrite = new AccountWrite();
        $this->entityStore->transaction(
            static function () use ($accountRepository, $accountWrite, $id, $seenName, $seenRole, $name, $role): void {
                $accountWrite->record($accountRepository->changeLocked($id, $seenName, $seenRole, $name, $role));
            },
        );

        return $accountWrite->outcome();
    }

    /**
     * `change()` calls this inside its transaction.
     *
     * @param int    $id       The member's id.
     * @param string $seenName The name the form showed.
     * @param string $seenRole The role the form showed, as stored.
     * @param string $name     The new name.
     * @param string $role     The new role, as stored.
     *
     * @throws RuntimeException         When a statement fails, or a role is no role.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return string An `AccountWrite` outcome.
     */
    public function changeLocked(int $id, string $seenName, string $seenRole, string $name, string $role): string
    {
        $newRole = $this->roles->stored($role);
        $admins = $this->lockedAdmins();
        $staffAccount = $this->locked($id);

        if (!$staffAccount instanceof StaffAccount) {
            return AccountWrite::MISSING;
        }

        if ($staffAccount->name !== $seenName || $staffAccount->role->value !== $seenRole) {
            return AccountWrite::STALE;
        }

        if ($this->leavesNoAdmin($staffAccount, $newRole === Role::Admin, $admins)) {
            return AccountWrite::LAST_ADMIN;
        }

        $this->entityStore->update($this->staffTable->type(), $id, ['name' => $name, 'role' => $newRole->value]);

        return AccountWrite::SAVED;
    }

    /**
     * Deactivates the member as of the database's today, or reactivates them; deactivating the last active admin is
     * refused. A member already in that state stays as they are.
     *
     * @param int  $id     The member's id.
     * @param bool $active Whether they may sign in from now on.
     *
     * @throws Throwable When a statement fails; the transaction is rolled back first.
     *
     * @return string An `AccountWrite` outcome: saved, last-admin or missing.
     */
    public function activate(int $id, bool $active): string
    {
        $accountRepository = $this;
        $accountWrite = new AccountWrite();
        $this->entityStore->transaction(static function () use ($accountRepository, $accountWrite, $id, $active): void {
            $accountWrite->record($accountRepository->activateLocked($id, $active));
        });

        return $accountWrite->outcome();
    }

    /**
     * `activate()` calls this inside its transaction.
     *
     * @param int  $id     The member's id.
     * @param bool $active Whether they may sign in from now on.
     *
     * @throws RuntimeException         When a statement fails, or a role is no role.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return string An `AccountWrite` outcome.
     */
    public function activateLocked(int $id, bool $active): string
    {
        $admins = $this->lockedAdmins();
        $staffAccount = $this->locked($id);

        if (!$staffAccount instanceof StaffAccount) {
            return AccountWrite::MISSING;
        }

        if ($staffAccount->active() === $active) {
            return AccountWrite::SAVED;
        }

        if ($this->leavesNoAdmin($staffAccount, $active, $admins)) {
            return AccountWrite::LAST_ADMIN;
        }

        $day = $active
            ? null
            : $this->today();
        $this->entityStore->update($this->staffTable->type(), $id, ['deactivated_on' => $day]);

        return AccountWrite::SAVED;
    }

    /**
     * @param int $id A member's id.
     *
     * @throws RuntimeException         When the query fails, or the stored role is no role.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return StaffAccount|null The member, their row locked until the transaction ends; null when no one has that id.
     */
    private function locked(int $id): ?StaffAccount
    {
        return $this->first($this->entityStore->query($this->staffTable->type())->where('id', $id)->forUpdate());
    }

    /**
     * @param EntityQuery $entityQuery A query over the users.
     *
     * @throws RuntimeException When the query fails, or the stored role is no role.
     *
     * @return StaffAccount|null The first member it finds; null when it finds none.
     */
    private function first(EntityQuery $entityQuery): ?StaffAccount
    {
        $row = $entityQuery->first();

        return $row instanceof Row
            ? $this->account($row)
            : null;
    }

    /**
     * @param StaffAccount $staffAccount The member, locked.
     * @param bool         $staysAdmin   Whether they stay an active admin after the write.
     * @param int          $admins       How many active admins there are, locked.
     *
     * @return bool Whether the write takes away the last active admin: the member is one, stops being one, and no other
     *              is left.
     */
    private function leavesNoAdmin(StaffAccount $staffAccount, bool $staysAdmin, int $admins): bool
    {
        return $staffAccount->activeAdmin() && !$staysAdmin && $admins < 2;
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int How many active admins there are, their rows locked by id until the transaction ends.
     */
    private function lockedAdmins(): int
    {
        $entityQuery = $this->entityStore->query($this->staffTable->type())
            ->where('role', Role::Admin->value)
            ->where('deactivated_on', null)
            ->orderBy('id')
            ->forUpdate();

        return count($entityQuery->rows());
    }

    /**
     * @throws RuntimeException         When the query fails, or the view gives no row, which it always gives.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return string The database's today, `YYYY-MM-DD`.
     */
    private function today(): string
    {
        return $this->entityStore->query($this->staffTable->today())->one()->string('today');
    }

    /**
     * @param Row $row A `users` row.
     *
     * @throws RuntimeException When a column is missing, or its role is no role.
     *
     * @return StaffAccount The member it holds.
     */
    private function account(Row $row): StaffAccount
    {
        return new StaffAccount(
            $row->int('id'),
            $row->string('email'),
            $row->string('name'),
            $this->roles->stored($row->string('role')),
            $row->stringOrNull('deactivated_on') ?? '',
        );
    }
}
