<?php

declare(strict_types=1);

namespace App\Theme\Components\TableRow;

/**
 * A row's box (#29), printed before its cell's text: it ticks the row for the form a page's action bar holds, posts its
 * value there as one of the form's list, and keeps the page's selection in step. A refused row says why under it.
 */
final readonly class RowBox
{
    /**
     * @param string $form    The id of the form it posts with.
     * @param string $name    The list it posts in, such as `vins[]`.
     * @param string $value   What it posts: its record's key.
     * @param string $label   What it ticks, for a screen reader (`Select the 2019 Honda Civic`).
     * @param bool   $checked Whether it is ticked as it arrives.
     * @param string $error   Why its row was refused; empty for none.
     */
    public function __construct(
        public string $form,
        public string $name,
        public string $value,
        public string $label,
        public bool $checked,
        public string $error,
    ) {
    }
}
