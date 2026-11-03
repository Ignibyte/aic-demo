<?php

declare(strict_types=1);

namespace App\Theme\Components\EditRow;

/**
 * One cell of a row as a form: its text, or its field in place of it; the cell that holds the form's buttons says so.
 * The field is a list of one rather than a nullable property: the template reads several of its properties inside
 * nested conditions and a loop, and Psalm (gate:13) does not carry a `!== null` test on a property chain into them in
 * the escaped template (`PossiblyNullPropertyFetch` on `$editCell->editField->…`), while a loop variable is typed.
 */
final readonly class EditCell
{
    /**
     * @param string          $text    What it shows when it holds no field.
     * @param bool            $mono    Whether it is set monospace (an identifier).
     * @param bool            $numeric Whether it is a number, set right.
     * @param list<EditField> $fields  Its field, alone; empty for a cell the row shows as it is.
     * @param bool            $actions Whether it holds the form, with Save and Cancel.
     */
    public function __construct(
        public string $text,
        public bool $mono,
        public bool $numeric,
        public array $fields,
        public bool $actions,
    ) {
    }
}
