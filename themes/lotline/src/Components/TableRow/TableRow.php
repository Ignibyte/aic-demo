<?php

declare(strict_types=1);

namespace App\Theme\Components\TableRow;

use AIC\Component\Contracts\Component;

/**
 * One row of a data table, from table-row.phtml. A gone row (#29) is set apart, struck through; a row whose box is
 * refused is tinted by the template.
 */
final readonly class TableRow implements Component
{
    /**
     * @param list<TableCell> $cells Its cells, in column order.
     * @param bool            $gone  Whether it was deleted, or is no longer there, and is struck through.
     */
    public function __construct(
        public array $cells,
        public bool $gone = false,
    ) {
    }
}
