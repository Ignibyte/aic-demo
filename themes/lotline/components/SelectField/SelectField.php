<?php

declare(strict_types=1);

namespace App\Theme\Components\SelectField;

use AIC\Component\Contracts\Component;

/**
 * A labelled select, from select-field.phtml: a prompt option, then its options with the chosen one selected, and its
 * error below.
 */
final readonly class SelectField implements Component
{
    /**
     * @param string             $id      The control's id.
     * @param string             $name    The field's name.
     * @param string             $label   The label.
     * @param string             $prompt  The empty option's text.
     * @param list<SelectOption> $options The options, in order.
     * @param string             $error   What is wrong with the choice; empty when nothing is.
     * @param bool               $compact Whether it is the small filter-bar size.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $label,
        public string $prompt,
        public array $options,
        public string $error,
        public bool $compact,
    ) {
    }
}
