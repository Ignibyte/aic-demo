<?php

declare(strict_types=1);

namespace App\Leads\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use App\Leads\Entity\Seller;
use App\Staff\Entity\Role;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

/**
 * The salespeople leads go to (#18): the users whose role is `salesperson`, read through staff's exported entity type.
 * Staff keeps nothing else about them, so these are the active ones.
 */
final readonly class SellerRepository
{
    /**
     * @param EntityStore $entityStore The tables.
     * @param StaffTable  $staffTable  The users' type.
     */
    public function __construct(
        private EntityStore $entityStore,
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @throws QueryException           When the users cannot be read.
     * @throws InvalidArgumentException When the users' type is malformed.
     *
     * @return list<Seller> The salespeople, by name.
     */
    public function all(): array
    {
        $sellers = [];

        foreach ($this->salespeople()->orderBy('name')->orderBy('id')->rows() as $row) {
            $sellers[] = $this->seller($row);
        }

        return $sellers;
    }

    /**
     * Locks the salespeople's rows with `FOR UPDATE`. The lock lasts only while a transaction is open around the call:
     * `LeadRepository::record()` opens one, takes this lock, and only then reads the newest lead, so a lead recorded at
     * the same moment waits here and then sees this one.
     *
     * @throws QueryException           When the users cannot be read.
     * @throws InvalidArgumentException When the users' type is malformed.
     *
     * @return list<int> The salespeople's ids, ascending.
     */
    public function locked(): array
    {
        $ids = [];

        foreach ($this->salespeople()->orderBy('id')->forUpdate()->rows() as $row) {
            $ids[] = $row->int('id');
        }

        return $ids;
    }

    /**
     * @param list<int> $ids  The salespeople's ids, ascending.
     * @param int       $last The newest lead's salesperson; 0 for none.
     *
     * @return int|null The first salesperson after that one, wrapping round to the first; null when there is none.
     */
    public function after(array $ids, int $last): ?int
    {
        foreach ($ids as $id) {
            if ($id > $last) {
                return $id;
            }
        }

        return $ids === []
            ? null
            : $ids[0];
    }

    /**
     * @param Row $row A user's row.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return Seller The salesperson.
     */
    private function seller(Row $row): Seller
    {
        return new Seller($row->int('id'), $row->string('email'), $row->string('name'));
    }

    /**
     * @throws InvalidArgumentException When the users' type is malformed.
     *
     * @return EntityQuery The users whose role is `salesperson`.
     */
    private function salespeople(): EntityQuery
    {
        return $this->entityStore->query($this->staffTable->type())->where('role', Role::Salesperson->value);
    }
}
