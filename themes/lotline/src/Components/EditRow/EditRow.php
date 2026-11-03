<?php

declare(strict_types=1);

namespace App\Theme\Components\EditRow;

use AIC\Component\Contracts\Component;

/**
 * A list's row as a small form (#28): the row's cells, some of them fields, and the form they join by its id, with
 * Save and Cancel in the cell that says so. Enter in a field or Save sends the form with `@put` to `save`, once while a
 * save is out; Escape in a field or Cancel asks `cancel` for the row shown again with `@get`. A field carries
 * `data-ignore-morph` only while it is typed into, so an answer that lands meanwhile leaves what was typed.
 */
final readonly class EditRow implements Component
{
    /**
     * @param string         $form   The form's id, which the fields join.
     * @param string         $save   Where the form is sent with `@put`.
     * @param string         $cancel Where the row is asked for again with `@get`.
     * @param list<EditCell> $cells  The row's cells, in the table's order.
     * @param string         $error  Why the row was refused for a value it shows no field for; empty for none.
     */
    public function __construct(
        public string $form,
        public string $save,
        public string $cancel,
        public array $cells,
        public string $error,
    ) {
    }
}
