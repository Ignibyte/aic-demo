<?php

declare(strict_types=1);

namespace App\Theme\Components\SelectField;

/**
 * One option of a select field: its value, its text, and whether it is the chosen one.
 */
final readonly class SelectOption
{
    /**
     * @param string $value    Its value.
     * @param string $label    Its text.
     * @param bool   $selected Whether it is chosen.
     */
    public function __construct(
        public string $value,
        public string $label,
        public bool $selected,
    ) {
    }
}
