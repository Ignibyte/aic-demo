<?php

declare(strict_types=1);

namespace App\Theme\Components\TableRow;

use AIC\Component\Contracts\Component;

/**
 * One row of a data table, from table-row.phtml.
 */
final readonly class TableRow implements Component
{
    /**
     * @param list<TableCell> $cells Its cells, in column order.
     */
    public function __construct(
        public array $cells,
    ) {
    }
}
