<?php

declare(strict_types=1);

namespace App\Theme\Components\CheckGroup;

/**
 * One box of a check group: its id, the name it posts under, its label and whether it is ticked.
 */
final readonly class CheckOption
{
    /**
     * @param string $id      The box's id, which its label names.
     * @param string $name    The field it posts as.
     * @param string $label   Its text.
     * @param bool   $checked Whether it is ticked.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $label,
        public bool $checked,
    ) {
    }
}
