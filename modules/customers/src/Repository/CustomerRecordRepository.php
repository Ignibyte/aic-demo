<?php

declare(strict_types=1);

namespace App\Customers\Repository;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Customers\Entity\Customer;
use App\Customers\Entity\CustomerDetails;
use App\Customers\Service\CustomerRows;

/**
 * One customer at a time, by its id: read, checked for a taken email, made, changed and deleted, each statement a
 * fixed text with its values bound.
 */
final readonly class CustomerRecordRepository
{
    /**
     * Reads the customer with an id.
     */
    private const string FIND = 'SELECT ' . CustomerRows::SELECTED . ' FROM customers WHERE id = ? LIMIT 1';

    /**
     * Counts the customers but one that hold an email.
     */
    private const string TAKEN = 'SELECT count(*)::integer AS total FROM customers WHERE email = ? AND id <> ?';

    /**
     * Makes a customer, unless its email is taken, and gives its id.
     */
    private const string CREATE = 'INSERT INTO customers (' . CustomerRows::SAVED . ', created_at, updated_at)'
        . ' VALUES (?, ?, ?, ?, ?, now(), now()) ON CONFLICT (email) DO NOTHING RETURNING id';

    /**
     * Gives the customer with an id every detail.
     */
    private const string UPDATE = 'UPDATE customers SET name = ?, email = ?, phone = ?, address = ?, notes = ?,'
        . ' updated_at = now() WHERE id = ?';

    /**
     * Deletes the customer with an id.
     */
    private const string DELETE = 'DELETE FROM customers WHERE id = ?';

    /**
     * @param Database     $database     The database.
     * @param CustomerRows $customerRows Reads a row into a customer, and details into their values.
     */
    public function __construct(
        private Database $database,
        private CustomerRows $customerRows = new CustomerRows(),
    ) {
    }

    /**
     * @param int $id A customer's id.
     *
     * @throws QueryException When the query fails.
     *
     * @return Customer|null The customer with it; null when none has it.
     */
    public function find(int $id): ?Customer
    {
        foreach ($this->database->fetchAll(self::FIND, [$id]) as $row) {
            return $this->customerRows->customer($row);
        }

        return null;
    }

    /**
     * @param string $email    An email, trimmed and in small letters.
     * @param int    $exceptId The id of the customer being changed, which may keep it; 0 for a new customer.
     *
     * @throws QueryException When the query fails.
     *
     * @return bool Whether another customer has it.
     */
    public function taken(string $email, int $exceptId): bool
    {
        return $this->database->fetchOne(self::TAKEN, [$email, $exceptId])->int('total') > 0;
    }

    /**
     * @param CustomerDetails $customerDetails A new customer's details.
     *
     * @throws QueryException When the statement fails.
     *
     * @return int|null The new customer's id; null when its email was taken first.
     */
    public function create(CustomerDetails $customerDetails): ?int
    {
        foreach ($this->database->fetchAll(self::CREATE, $this->customerRows->values($customerDetails)) as $row) {
            return $this->createdId($row);
        }

        return null;
    }

    /**
     * @param int             $id              The customer's id.
     * @param CustomerDetails $customerDetails Every detail it is to have.
     *
     * @throws QueryException When the statement fails, such as on an email another customer took since the check,
     *                        which `CustomerSaves` answers as that refusal.
     *
     * @return bool Whether it was changed; false when no customer has the id any more.
     */
    public function update(int $id, CustomerDetails $customerDetails): bool
    {
        return $this->database->execute(self::UPDATE, [...$this->customerRows->values($customerDetails), $id]) === 1;
    }

    /**
     * @param int $id A customer's id.
     *
     * @throws QueryException When the statement fails.
     *
     * @return bool Whether the customer was deleted; false when none has the id.
     */
    public function delete(int $id): bool
    {
        return $this->database->execute(self::DELETE, [$id]) === 1;
    }

    /**
     * Reads the row through a native `Row` parameter, as `CustomerRows::customer()` does: elephc reads no phpdoc, so a
     * row straight off a `fetchAll()` list has no type to it. No AIC ticket carries this yet.
     *
     * @param Row $row The row `CREATE` returned.
     *
     * @throws QueryException When it has no id.
     *
     * @return int The new id.
     */
    private function createdId(Row $row): int
    {
        return $row->int('id');
    }
}
