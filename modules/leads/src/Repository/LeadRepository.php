<?php

declare(strict_types=1);

namespace App\Leads\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use App\Customers\Entity\CustomerTable;
use App\Leads\Entity\Lead;
use App\Leads\Entity\LeadDetails;
use App\Leads\Entity\LeadTable;
use App\Leads\Entity\LeadWrite;
use App\Leads\Service\LeadRows;
use InvalidArgumentException;
use Throwable;

/**
 * The leads (#18): one recorded for the salesperson whose turn it is, one found with its car, its salesperson and its
 * customer, the open ones a filter keeps, and a lead reassigned or closed. Customers are read through their module's
 * exported entity type, for the email match.
 */
final readonly class LeadRepository
{
    /**
     * A lead still to be dealt with.
     */
    private const string OPEN = 'open';

    /**
     * @param EntityStore      $entityStore      The tables.
     * @param SellerRepository $sellerRepository Whose turn it is.
     * @param LeadTable        $leadTable        The leads' type.
     * @param CustomerTable    $customerTable    The customers' type.
     * @param LeadRows         $leadRows         Reads a lead's row.
     */
    public function __construct(
        private EntityStore $entityStore,
        private SellerRepository $sellerRepository,
        private LeadTable $leadTable = new LeadTable(),
        private CustomerTable $customerTable = new CustomerTable(),
        private LeadRows $leadRows = new LeadRows(),
    ) {
    }

    /**
     * Records a lead for its salesperson in one transaction, so a lead sent at the same moment waits for this one and
     * goes to the next salesperson.
     *
     * @param int         $vehicleId   The car's row.
     * @param LeadDetails $leadDetails What the visitor sent, checked.
     * @param int         $customerId  The customer whose email it is; 0 for none.
     *
     * @throws Throwable When the leads cannot be read or written, or a value does not fit its field; the transaction is
     *                   rolled back first.
     *
     * @return int The new lead's id.
     */
    public function record(int $vehicleId, LeadDetails $leadDetails, int $customerId): int
    {
        $values = [
            'customer_id' => $customerId === 0 ? null : $customerId,
            'drive_at' => $leadDetails->driveAt === '' ? null : $leadDetails->driveAt,
            'drive_on' => $leadDetails->driveOn === '' ? null : $leadDetails->driveOn,
            'email' => $leadDetails->email,
            'kind' => $leadDetails->leadKind->value,
            'message' => $leadDetails->message,
            'name' => $leadDetails->name,
            'phone' => $leadDetails->phone,
            'status' => self::OPEN,
            'vehicle_id' => $vehicleId,
        ];
        $leadRepository = $this;
        $leadWrite = new LeadWrite();
        $this->entityStore->transaction(static function () use ($leadRepository, $values, $leadWrite): void {
            $leadWrite->hold($leadRepository->inTurn($values));
        });

        return $leadWrite->written();
    }

    /**
     * Inserts a lead for the salesperson after the newest lead's: it locks the salespeople's rows first, then reads the
     * newest lead, so a lead sent at the same moment waits and then sees this one. The lock holds only inside a
     * transaction; `record()` calls this inside its own, and nothing else may call it, though PHP cannot keep a closure's
     * callee private here.
     *
     * @param array<string, string|int|null> $values The lead's values but its salesperson.
     *
     * @throws QueryException           When the tables cannot be read or written.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return int The new lead's id.
     */
    public function inTurn(array $values): int
    {
        $ids = $this->sellerRepository->locked();
        $newest = $this->entityStore->query($this->leadTable->type())->orderByDescending('id')->limit(1)->first();
        $last = $newest instanceof Row
            ? $this->leadRows->sellerId($newest)
            : 0;
        $values['salesperson_id'] = $this->sellerRepository->after($ids, $last);

        return $this->entityStore->insert($this->leadTable->type(), $values);
    }

    /**
     * @param string $email An email, lower case.
     *
     * @throws QueryException           When the customers cannot be read.
     * @throws InvalidArgumentException When the customers' type is malformed.
     *
     * @return int The customer with that email; 0 for none.
     */
    public function customerId(string $email): int
    {
        $row = $this->entityStore->query($this->customerTable->type())->where('email', $email)->first();

        return $row instanceof Row
            ? $row->int('id')
            : 0;
    }

    /**
     * @param int $id A lead's id.
     *
     * @throws QueryException           When the leads cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return Lead|null The lead; null when there is none.
     */
    public function find(int $id): ?Lead
    {
        $row = $this->joined()->where('id', $id)->first();

        return $row instanceof Row
            ? $this->leadRows->lead($row)
            : null;
    }

    /**
     * @param string $kind   A kind's value; empty for every kind.
     * @param string $seller A salesperson's email; empty for every one.
     * @param int    $limit  The most to read.
     *
     * @throws QueryException           When the leads cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return list<Lead> The open leads the filter keeps, newest first.
     */
    public function open(string $kind, string $seller, int $limit): array
    {
        $leads = [];

        foreach ($this->filtered($kind, $seller)->orderByDescending('id')->limit($limit)->rows() as $row) {
            $leads[] = $this->leadRows->lead($row);
        }

        return $leads;
    }

    /**
     * @param string $kind   A kind's value; empty for every kind.
     * @param string $seller A salesperson's email; empty for every one.
     *
     * @throws QueryException           When the leads cannot be counted.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return int How many open leads the filter keeps.
     */
    public function countOpen(string $kind, string $seller): int
    {
        return $this->filtered($kind, $seller)->totals()->count();
    }

    /**
     * @param int $id       A lead's id.
     * @param int $sellerId A salesperson's user id.
     *
     * @throws QueryException           When the lead cannot be written.
     * @throws EntityNotFoundException  When the lead went meanwhile.
     * @throws InvalidArgumentException When the leads' type is malformed.
     */
    public function assign(int $id, int $sellerId): void
    {
        $this->entityStore->update($this->leadTable->type(), $id, ['salesperson_id' => $sellerId]);
    }

    /**
     * @param int $id A lead's id.
     *
     * @throws QueryException           When the lead cannot be written.
     * @throws EntityNotFoundException  When the lead went meanwhile.
     * @throws InvalidArgumentException When the leads' type is malformed.
     */
    public function close(int $id): void
    {
        $this->entityStore->update($this->leadTable->type(), $id, ['status' => 'closed']);
    }

    /**
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return EntityQuery The leads with their car, and their salesperson and customer when they have one.
     */
    private function joined(): EntityQuery
    {
        return $this->entityStore->query($this->leadTable->type())
            ->with('vehicle_id', 'v')
            ->with('salesperson_id', 'u', true)
            ->with('customer_id', 'c', true);
    }

    /**
     * @param string $kind   A kind's value; empty for every kind.
     * @param string $seller A salesperson's email; empty for every one.
     *
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return EntityQuery The open leads the filter keeps.
     */
    private function filtered(string $kind, string $seller): EntityQuery
    {
        $entityQuery = $this->joined()->where('status', self::OPEN);

        if ($kind !== '') {
            $entityQuery = $entityQuery->where('kind', $kind);
        }

        return $seller === ''
            ? $entityQuery
            : $entityQuery->where('u.email', $seller);
    }
}
