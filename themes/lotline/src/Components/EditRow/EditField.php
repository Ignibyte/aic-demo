<?php

declare(strict_types=1);

namespace App\Theme\Components\EditRow;

use App\Theme\Components\SelectField\SelectOption;

/**
 * One field of a row as a form: a text box (`text`, `tel`) or a select of its options, labelled for a reader since a
 * table cell shows no label, with the error that refused it. The first field takes focus, its text selected, when the
 * row arrives; a refused one takes it after the refusal.
 */
final readonly class EditField
{
    /**
     * @param string             $id      Its id.
     * @param string             $name    The name it is sent under.
     * @param string             $label   What it is, for a reader.
     * @param string             $type    `text`, `tel` or `select`.
     * @param string             $value   Its value; for a select, the chosen option says it.
     * @param list<SelectOption> $options A select's options; empty for a text box.
     * @param string             $error   Why it was refused; empty when it was not.
     * @param bool               $first   Whether it takes focus when the row arrives.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $label,
        public string $type,
        public string $value,
        public array $options,
        public string $error,
        public bool $first,
    ) {
    }
}
