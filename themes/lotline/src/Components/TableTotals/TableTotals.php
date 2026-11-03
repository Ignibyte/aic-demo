<?php

declare(strict_types=1);

namespace App\Theme\Components\TableTotals;

use AIC\Component\Contracts\Component;

/**
 * A list's totals line (#29), from table-totals.phtml: what the table shows (`60 vehicles, page 1 of 4`), the matching
 * records counted by status, and what a bulk edit just did or why it was refused. It declares the page's selection
 * afresh with the keys of the rows the page shows, so a refresh that shows other rows starts it empty, and it posts
 * those keys with the action bar's form, so a bulk answer knows the rows the page still shows.
 */
final readonly class TableTotals implements Component
{
    /**
     * @param string       $id       The table's id; the lines are `<id>-summary`, `<id>-counts` and `<id>-outcome`.
     * @param string       $summary  What the table shows.
     * @param string       $counts   The matching records by status (`42 in stock · 10 on hold`); empty for none.
     * @param string       $outcome  What a bulk edit did, or why it was refused; empty for none.
     * @param bool         $refused  Whether the outcome is a refusal, said as an alert.
     * @param string       $form     The id of the action bar's form the page's keys post with.
     * @param string       $name     The list they post in, such as `page[]`.
     * @param list<string> $page     The keys of the rows the page shows, in order.
     * @param string       $pageList The same keys as an expression's list items, each quoted (`'A','B'`).
     */
    public function __construct(
        public string $id,
        public string $summary,
        public string $counts,
        public string $outcome,
        public bool $refused,
        public string $form,
        public string $name,
        public array $page,
        public string $pageList,
    ) {
    }
}
