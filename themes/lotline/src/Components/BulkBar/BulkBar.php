<?php

declare(strict_types=1);

namespace App\Theme\Components\BulkBar;

use AIC\Component\Contracts\Component;
use App\Theme\Components\SelectField\SelectOption;

/**
 * A list's action bar (#29), from bulk-bar.phtml: a form that acts at once on the rows ticked in the table, whose boxes
 * post with it by its id. With script its controls wait for a tick, it counts the ticked rows, a delete asks first in a
 * card, and Apply sends one action naming each ticked row's anchor and the totals line's, so only those come back; a
 * patch of the results (a filter, a sort, a page) empties the selection. Without script it is a plain form, and a
 * delete is answered with the ticked rows and the bar again, its card open, so Delete them posts the delete confirmed
 * and Keep them goes back to the list.
 */
final readonly class BulkBar implements Component
{
    /**
     * @param string             $id          The form's id, which the rows' boxes name.
     * @param string             $action      Where it posts.
     * @param string             $token       The visitor's form token.
     * @param string             $back        The list it was shown on, path and query, which a plain post goes back to.
     * @param string             $label       The choice's label (`With the ticked vehicles`).
     * @param list<SelectOption> $choices     What it can do, each a value the server reads and its text.
     * @param string             $rowAnchor   What a row's anchor starts with; the key follows (`vehicle-`).
     * @param string             $totals      The totals line's anchor.
     * @param string             $results     The results' anchor, whose patch empties the selection.
     * @param BulkConfirm        $bulkConfirm The confirm card a delete asks with first.
     */
    public function __construct(
        public string $id,
        public string $action,
        public string $token,
        public string $back,
        public string $label,
        public array $choices,
        public string $rowAnchor,
        public string $totals,
        public string $results,
        public BulkConfirm $bulkConfirm,
    ) {
    }
}
