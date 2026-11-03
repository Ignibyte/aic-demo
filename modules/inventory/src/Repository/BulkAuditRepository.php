<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Entity\Application\EntityStore;
use AIC\Notification\Application\Notifier;
use App\Inventory\Entity\BulkAudit;
use App\Inventory\Entity\BulkEdit;
use App\Inventory\Entity\Vehicle;
use App\Staff\Entity\Role;
use App\Staff\Repository\AccountRepository;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

use function count;

/**
 * A bulk edit's audit, delivered (#29): one `notify` job per active sales manager, for the database channel alone,
 * through AIC's Notifier. The queue writes through the connection the batch's transaction holds, so the jobs commit with
 * the change or not at all. The sales managers come from staff's accounts.
 */
final readonly class BulkAuditRepository
{
    /**
     * Staff's accounts, the sales managers among them.
     */
    private AccountRepository $accountRepository;

    /**
     * @param Notifier    $notifier    Queues each delivery.
     * @param EntityStore $entityStore The entity store, through which staff's accounts are read; built on here, so the
     *                                 bulk edit's generated handler builds no more classes than PHPMD allows.
     */
    public function __construct(
        private Notifier $notifier,
        EntityStore $entityStore,
    ) {
        $this->accountRepository = new AccountRepository($entityStore);
    }

    /**
     * @param BulkEdit      $bulkEdit A bulk edit that was just written.
     * @param list<Vehicle> $vehicles The vehicles it was written to.
     * @param string        $member   Who made it, by name.
     *
     * @throws RuntimeException         When the accounts cannot be read, a stored role is no role, or a job cannot be queued
     *                                  (`QueryException`).
     * @throws JsonException            When a delivery's payload cannot be encoded.
     * @throws InvalidArgumentException When the accounts' entity type is malformed.
     */
    public function send(BulkEdit $bulkEdit, array $vehicles, string $member): void
    {
        $bulkAudit = new BulkAudit($member, $bulkEdit->done(count($vehicles)), $vehicles);

        foreach ($this->accountRepository->all() as $staffAccount) {
            if ($staffAccount->role !== Role::SalesManager || !$staffAccount->active()) {
                continue;
            }

            $this->notifier->send($bulkAudit->notification($staffAccount), ['database']);
        }
    }
}
