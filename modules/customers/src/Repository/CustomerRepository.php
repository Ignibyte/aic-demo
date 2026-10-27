<?php

declare(strict_types=1);

namespace App\Customers\Repository;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use App\Customers\Entity\Customer;
use App\Customers\Entity\CustomerDetails;
use App\Customers\Service\CustomerRows;

use function str_replace;

/**
 * The customers table for the list and the seed: the customers a search keeps, counted and a page at a time by name,
 * and the seed's save by email, which the table holds unique. Every value travels as a bound parameter.
 */
final readonly class CustomerRepository
{
    /**
     * Inserts a customer, or on its email gives the row every other detail.
     */
    private const string UPSERT = 'INSERT INTO customers (' . CustomerRows::SAVED . ', created_at, updated_at)'
        . ' VALUES (?, ?, ?, ?, ?, now(), now())'
        . ' ON CONFLICT (email) DO UPDATE SET name = EXCLUDED.name, phone = EXCLUDED.phone,'
        . ' address = EXCLUDED.address, notes = EXCLUDED.notes, updated_at = now()';

    /**
     * The condition a search adds, one `?` for each column it looks in.
     */
    private const string SEARCH = ' WHERE (name ILIKE ? OR email ILIKE ? OR phone ILIKE ?)';

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
     * Makes the customer, or gives the one with its email every other detail.
     *
     * @param CustomerDetails $customerDetails The customer's details.
     *
     * @throws QueryException When the statement fails.
     */
    public function save(CustomerDetails $customerDetails): void
    {
        $this->database->execute(self::UPSERT, $this->customerRows->values($customerDetails));
    }

    /**
     * @param string $search Text to look for in name, email and phone; empty for every customer.
     *
     * @throws QueryException When the query fails.
     *
     * @return int How many customers it keeps.
     */
    public function count(string $search): int
    {
        $sql = 'SELECT count(*)::integer AS total FROM customers' . $this->where($search);

        return $this->database->fetchOne($sql, $this->patterns($search))->int('total');
    }

    /**
     * @param string $search Text to look for in name, email and phone; empty for every customer.
     * @param int    $limit  How many customers a page holds.
     * @param int    $offset How many come before the page.
     *
     * @throws QueryException When the query fails.
     *
     * @return list<Customer> The page's customers, by name and then by id.
     */
    public function page(string $search, int $limit, int $offset): array
    {
        $sql = 'SELECT ' . CustomerRows::SELECTED . ' FROM customers' . $this->where($search)
            . ' ORDER BY name ASC, id ASC LIMIT ? OFFSET ?';
        $customers = [];

        foreach ($this->database->fetchAll($sql, [...$this->patterns($search), $limit, $offset]) as $row) {
            $customers[] = $this->customerRows->customer($row);
        }

        return $customers;
    }

    /**
     * @param string $search The search text.
     *
     * @return string The search's condition; empty for none.
     */
    private function where(string $search): string
    {
        return $search === ''
            ? ''
            : self::SEARCH;
    }

    /**
     * @param string $search The search text.
     *
     * @return list<string> The text as an `ILIKE` pattern for each `?` of `SEARCH`, with `\`, `%` and `_` escaped so it
     *                      matches as written; empty for no search.
     */
    private function patterns(string $search): array
    {
        if ($search === '') {
            return [];
        }

        $pattern = '%' . str_replace('_', '\\_', str_replace('%', '\\%', str_replace('\\', '\\\\', $search))) . '%';

        return [$pattern, $pattern, $pattern];
    }
}
