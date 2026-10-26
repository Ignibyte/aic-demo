<?php

declare(strict_types=1);

namespace App\Theme\Components\DataTable;

/**
 * One column head: its text, and when it sorts, the link that sorts it, its `aria-sort` and its mark.
 */
final readonly class TableColumn
{
    /**
     * @param string $label   Its text.
     * @param string $href    The list sorted by it; empty when it does not sort.
     * @param string $sort    `ascending`, `descending` or `none`.
     * @param string $mark    ` ▲` or ` ▼` on the sorted column; empty elsewhere.
     * @param bool   $numeric Whether its cells are numbers, set right.
     */
    public function __construct(
        public string $label,
        public string $href,
        public string $sort,
        public string $mark,
        public bool $numeric,
    ) {
    }
}
