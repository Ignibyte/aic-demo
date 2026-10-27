<?php

declare(strict_types=1);

namespace App\Theme\Components\FormField;

use AIC\Component\Contracts\Component;

/**
 * A labelled text control, from form-field.phtml: an input of its type, or a textarea, its error below it and named by
 * the control. Compact fields sit in a filter bar.
 */
final readonly class FormField implements Component
{
    /**
     * @param string $id       The control's id.
     * @param string $name     The field's name.
     * @param string $label    The label.
     * @param string $type     The input type (`text`, `email`, `password`, `number`, `date`, `search`), or `textarea`.
     * @param string $value    The value shown.
     * @param string $error    What is wrong with the value; empty when nothing is.
     * @param bool   $required Whether the browser requires a value.
     * @param bool   $compact  Whether it is the small filter-bar size.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $label,
        public string $type,
        public string $value,
        public string $error,
        public bool $required,
        public bool $compact,
    ) {
    }
}
