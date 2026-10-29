<?php

declare(strict_types=1);

namespace App\Inbox\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

/**
 * A member of staff's user id (#19), read through staff's exported entity type: the session keeps their email, and a
 * notification is kept under the id.
 */
final readonly class MemberRepository
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
     * @param string $email A member of staff's email.
     *
     * @throws QueryException           When the users cannot be read.
     * @throws InvalidArgumentException When the users' type is malformed.
     *
     * @return int Their id; 0 when no member of staff has that email.
     */
    public function memberId(string $email): int
    {
        $row = $this->entityStore->query($this->staffTable->type())->where('email', $email)->first();

        return $row instanceof Row
            ? $row->int('id')
            : 0;
    }
}
