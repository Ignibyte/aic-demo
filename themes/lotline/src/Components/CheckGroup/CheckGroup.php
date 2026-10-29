<?php

declare(strict_types=1);

namespace App\Theme\Components\CheckGroup;

use AIC\Component\Contracts\Component;

/**
 * A labelled group of checkboxes, from check-group.phtml: a legend, the boxes in a row that wraps, each with its label,
 * and the group's error below them. Each box posts `1` under its own name when it is ticked and nothing when it is not,
 * such as the dealership's open days (#22).
 */
final readonly class CheckGroup implements Component
{
    /**
     * @param string            $id      The group's id, which its error's id extends.
     * @param string            $legend  What the boxes choose.
     * @param list<CheckOption> $options The boxes, in order.
     * @param string            $error   What is wrong with the choice; empty when nothing is.
     */
    public function __construct(
        public string $id,
        public string $legend,
        public array $options,
        public string $error,
    ) {
    }
}
