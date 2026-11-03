<?php

declare(strict_types=1);

namespace App\Theme\Components\DataTable;

/**
 * One column head: its text, and when it sorts, the link that sorts it, its `aria-sort` and its mark. The first may hold
 * the page's box (#29), which ticks or unticks every row the page shows; it shows only with script.
 */
final readonly class TableColumn
{
    /**
     * @param string $label   Its text.
     * @param string $href    The list sorted by it; empty when it does not sort.
     * @param string $sort    `ascending`, `descending` or `none`.
     * @param string $mark    ` ▲` or ` ▼` on the sorted column; empty elsewhere.
     * @param bool   $numeric Whether its cells are numbers, set right.
     * @param string $box     The page's box's label (`Select every vehicle on this page`); empty for no box.
     */
    public function __construct(
        public string $label,
        public string $href,
        public string $sort,
        public string $mark,
        public bool $numeric,
        public string $box = '',
    ) {
    }
}
