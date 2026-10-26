<?php

declare(strict_types=1);

namespace App\Site\Form;

/**
 * What the style guide's sample list was asked for: its search text, its status, its sort and its page, each empty
 * or 1 when absent.
 */
final readonly class ThemeQuery
{
    /**
     * @param string $search The search text.
     * @param string $status The status asked for (`in_stock`, `on_hold`, `sold`); empty for every one.
     * @param string $sort   `year`, `-year`, `price` or `-price`; empty for the list's own order.
     * @param int    $page   The page, from 1.
     */
    public function __construct(
        public string $search,
        public string $status,
        public string $sort,
        public int $page,
    ) {
    }
}
