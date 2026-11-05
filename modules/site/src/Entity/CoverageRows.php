<?php

declare(strict_types=1);

namespace App\Site\Entity;

/**
 * Every row of the Coverage page (#38), in `bin/aic code:coverage`'s order: the one value `config/coverage.php` returns,
 * so the catalog that requires the file checks it with a single `instanceof`.
 */
final readonly class CoverageRows
{
    /**
     * @param list<CoverageRow> $rows One row per surface `code:coverage` lists, in its order.
     */
    public function __construct(
        public array $rows,
    ) {
    }
}
