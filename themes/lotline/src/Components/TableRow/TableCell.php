<?php

declare(strict_types=1);

namespace App\Theme\Components\TableRow;

use App\Theme\Components\StatusBadge\BadgeTone;

/**
 * One cell: its text, and a link, a monospace face, a right-set number or a badge. An editable cell (#28) also names
 * the URL a click asks for its row's form: its link (the record's edit page) is then what a page without script follows.
 * A row's first cell may hold its box (#29), before its text: a list of one rather than a nullable property, as
 * `EditCell`'s field is, since Psalm (gate:13) does not carry a `!== null` test on a property chain into the escaped
 * template.
 */
final readonly class TableCell
{
    /**
     * @param string       $text      Its text.
     * @param string       $href      Where it links; empty for none.
     * @param bool         $mono      Whether it is set monospace (an identifier).
     * @param bool         $numeric   Whether it is a number, set right.
     * @param ?BadgeTone   $badgeTone Its badge's colour; null for plain text.
     * @param string       $edit      The URL a click on it asks for its row as a form (`@get`); empty when it is not editable.
     * @param list<RowBox> $boxes     The row's box, alone, before the text; empty for none.
     */
    public function __construct(
        public string $text,
        public string $href,
        public bool $mono,
        public bool $numeric,
        public ?BadgeTone $badgeTone,
        public string $edit = '',
        public array $boxes = [],
    ) {
    }
}
