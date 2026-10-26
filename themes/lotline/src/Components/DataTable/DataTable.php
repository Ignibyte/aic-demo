<?php

declare(strict_types=1);

namespace App\Theme\Components\DataTable;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A list of records, from data-table.phtml: a summary line, then a bordered daisyUI table whose sortable heads Tessera
 * answers in place, or a line saying nothing matched.
 */
final readonly class DataTable implements Component
{
    /**
     * @param string               $id      The table's id.
     * @param string               $summary What the table shows (`12 vehicles, newest first`).
     * @param string               $empty   What to say when no row matches.
     * @param string               $target  The regions a sort updates (`content`).
     * @param list<TableColumn>    $columns The column heads, in order.
     * @param list<KeyedComponent> $rows    The rows, each a TableRow keyed by its record.
     */
    public function __construct(
        public string $id,
        public string $summary,
        public string $empty,
        public string $target,
        public array $columns,
        public array $rows,
    ) {
    }
}
