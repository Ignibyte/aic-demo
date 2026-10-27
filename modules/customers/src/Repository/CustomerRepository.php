<?php

declare(strict_types=1);

namespace App\Customers\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use App\Customers\Entity\Customer;
use App\Customers\Entity\CustomerDetails;
use App\Customers\Entity\CustomerTable;
use App\Customers\Service\CustomerRows;
use InvalidArgumentException;

/**
 * The customers table for the list and the seed, through the entity store (#14): the customers a search keeps, counted
 * and a page at a time by name, and the seed's save by email, which the table holds unique.
 */
final readonly class CustomerRepository
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
     * Makes the customer, or gives the one with its email every other detail.
     *
     * @param CustomerDetails $customerDetails The customer's details.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     */
    public function save(CustomerDetails $customerDetails): void
    {
        $this->entityStore->upsert($this->customerTable->type(), $this->customerRows->values($customerDetails), 'email');
    }

    /**
     * @param string $search Text to look for in name, email and phone; empty for every customer.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int How many customers it keeps.
     */
    public function count(string $search): int
    {
        return $this->matching($search)->totals()->count();
    }

    /**
     * @param string $search Text to look for in name, email and phone; empty for every customer.
     * @param int    $limit  How many customers a page holds, at least 1.
     * @param int    $offset How many come before the page.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the limit is below 1 or the offset negative.
     *
     * @return list<Customer> The page's customers, by name and then by id.
     */
    public function page(string $search, int $limit, int $offset): array
    {
        $customers = [];

        foreach ($this->matching($search)->orderBy('name')->orderBy('id')->limit($limit)->offset($offset)->rows() as $row) {
            $customers[] = $this->customerRows->customer($row);
        }

        return $customers;
    }

    /**
     * @param string $search The search text.
     *
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return EntityQuery The customers whose name, email or phone holds it as typed, ignoring case; every one for none.
     */
    private function matching(string $search): EntityQuery
    {
        return $this->entityStore->query($this->customerTable->type())->search(['name', 'email', 'phone'], $search);
    }
}
