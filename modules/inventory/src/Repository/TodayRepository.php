<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use InvalidArgumentException;

/**
 * The database's today, read from the `inventory_today` view through a read-only entity type (#14). The vehicle form,
 * the sale form and the dashboard's version read it, so it is exported.
 */
final readonly class TodayRepository
{
    /**
     * @param EntityStore $entityStore The entity store.
     */
    public function __construct(
        private EntityStore $entityStore,
    ) {
    }

    /**
     * @throws QueryException           When the query fails, or the view gives no row, which it always gives.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return string The database's today, `YYYY-MM-DD`.
     */
    public function today(): string
    {
        $row = $this->entityStore->query(new EntityType('inventory_today', 'today', [], true))->first();

        if (!$row instanceof Row) {
            throw new QueryException('The inventory_today view gave no row.');
        }

        return $row->string('today');
    }
}
