<?php

declare(strict_types=1);

namespace App\Settings\Repository;

use AIC\Auth\Contracts\PasswordHasher;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use App\Settings\Entity\LinkTables;
use App\Settings\Entity\PasswordLink;
use App\Settings\Entity\SettingsWrite;
use App\Staff\Repository\StaffRepository;
use InvalidArgumentException;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * The password links (#22), through the entity store. Issuing one voids the member's links still unused, so only the
 * newest works. Using one is one `EntityStore::transaction()` that marks it used while it is unused (the one write that
 * can, so a link sets a password once however many posts race) and sets the member's password through staff's
 * `StaffRepository`, so a link is never spent without the password set nor the reverse. Whether its 24 hours have
 * passed is the `password_link_states` view's, by the database's clock.
 */
final readonly class LinkRepository
{
    /**
     * @param EntityStore     $entityStore     The entity store.
     * @param StaffRepository $staffRepository Sets a member's password.
     * @param PasswordHasher  $passwordHasher  Hashes the password chosen.
     * @param LinkTables      $linkTables      The links' table and view.
     */
    public function __construct(
        private EntityStore $entityStore,
        private StaffRepository $staffRepository,
        private PasswordHasher $passwordHasher,
        private LinkTables $linkTables = new LinkTables(),
    ) {
    }

    /**
     * @param int $userId The member whose password it sets.
     *
     * @throws QueryException           When a statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return int The new link's id; the member's older unused links are voided.
     */
    public function issue(int $userId): int
    {
        $entityType = $this->linkTables->links();
        $this->entityStore->query($entityType)->where('user_id', $userId)->where('used', false)->changes()->update(['used' => true]);

        return $this->entityStore->insert($entityType, ['used' => false, 'user_id' => $userId]);
    }

    /**
     * @param int $id A link's id.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return PasswordLink|null The link, used, expired or open; null when there is none.
     */
    public function find(int $id): ?PasswordLink
    {
        $row = $this->entityStore->query($this->linkTables->states())->where('id', $id)->first();

        return $row instanceof Row
            ? new PasswordLink($row->int('id'), $row->int('user_id'), $row->bool('used'), $row->bool('expired'))
            : null;
    }

    /**
     * @param PasswordLink $passwordLink An open link.
     * @param string       $password     The password its member chose, checked.
     *
     * @throws Throwable When a statement fails; the transaction is rolled back first.
     *
     * @return bool Whether it set the password; false when another post used the link first.
     */
    public function use(PasswordLink $passwordLink, #[SensitiveParameter]
        string $password, ): bool
    {
        $linkRepository = $this;
        $settingsWrite = new SettingsWrite();
        $this->entityStore->transaction(static function () use ($linkRepository, $settingsWrite, $passwordLink, $password): void {
            $settingsWrite->record($linkRepository->useLocked($passwordLink, $password));
        });

        return $settingsWrite->outcome() === SettingsWrite::SAVED;
    }

    /**
     * `use()` calls this inside its transaction.
     *
     * @param PasswordLink $passwordLink An open link.
     * @param string       $password     The password its member chose.
     *
     * @throws RuntimeException         When a statement fails, or the member is gone.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return string `SettingsWrite::SAVED`, or `SettingsWrite::STALE` when the link was used meanwhile.
     */
    public function useLocked(PasswordLink $passwordLink, #[SensitiveParameter]
        string $password, ): string
    {
        $changed = $this->entityStore->query($this->linkTables->links())
            ->where('id', $passwordLink->id)
            ->where('used', false)
            ->changes()
            ->update(['used' => true]);

        if ($changed !== 1) {
            return SettingsWrite::STALE;
        }

        $this->staffRepository->setPasswordHash($passwordLink->userId, $this->passwordHasher->hash($password));

        return SettingsWrite::SAVED;
    }
}
