<?php

declare(strict_types=1);

namespace App\Customers\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use App\Customers\Entity\Customer;
use App\Customers\Entity\CustomerDetails;
use App\Customers\Entity\CustomerTable;
use App\Customers\Service\CustomerRows;
use InvalidArgumentException;

/**
 * One customer at a time, by its id, through the entity store (#14): read, checked for a taken email, made, changed
 * and deleted.
 */
final readonly class CustomerRecordRepository
{
    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param CustomerTable $customerTable The `customers` entity type.
     * @param CustomerRows  $customerRows  Reads a row into a customer, and details into their values.
     */
    public function __construct(
        private EntityStore $entityStore,
        private CustomerTable $customerTable = new CustomerTable(),
        private CustomerRows $customerRows = new CustomerRows(),
    ) {
    }

    /**
     * @param int $id A customer's id.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return Customer|null The customer with it; null when none has it.
     */
    public function find(int $id): ?Customer
    {
        $row = $this->entityStore->query($this->customerTable->type())->where('id', $id)->first();

        return $row instanceof Row
            ? $this->customerRows->customer($row)
            : null;
    }

    /**
     * @param string $email    An email, trimmed and in small letters.
     * @param int    $exceptId The id of the customer being changed, which may keep it; 0 for a new customer.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return bool Whether another customer has it.
     */
    public function taken(string $email, int $exceptId): bool
    {
        return $this->entityStore->query($this->customerTable->type())
            ->where('email', $email)
            ->where('id', $exceptId, Comparison::NotEquals)
            ->totals()
            ->count() > 0;
    }

    /**
     * @param CustomerDetails $customerDetails A new customer's details.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return int|null The new customer's id; null when its email was taken first.
     */
    public function create(CustomerDetails $customerDetails): ?int
    {
        return $this->entityStore->insertIfAbsent($this->customerTable->type(), $this->customerRows->values($customerDetails), 'email');
    }

    /**
     * @param int             $id              The customer's id.
     * @param CustomerDetails $customerDetails Every detail it is to have.
     *
     * @throws QueryException           When the statement fails, such as on an email another customer took since the
     *                                  check, which `CustomerSaves` answers as that refusal.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return bool Whether it was changed; false when no customer has the id any more.
     */
    public function update(int $id, CustomerDetails $customerDetails): bool
    {
        return $this->entityStore->query($this->customerTable->type())
            ->where('id', $id)
            ->changes()
            ->update($this->customerRows->values($customerDetails)) === 1;
    }

    /**
     * @param int $id A customer's id.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return bool Whether the customer was deleted; false when none has the id.
     */
    public function delete(int $id): bool
    {
        return $this->entityStore->query($this->customerTable->type())->where('id', $id)->changes()->delete() === 1;
    }
}
