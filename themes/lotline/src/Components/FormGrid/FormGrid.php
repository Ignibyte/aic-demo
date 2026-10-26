<?php

declare(strict_types=1);

namespace App\Theme\Components\FormGrid;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A form's fields in a grid, from form-grid.phtml: one column, two from `sm`, three from `lg`.
 */
final readonly class FormGrid implements Component
{
    /**
     * @param list<KeyedComponent> $fields The fields, each keyed by its name.
     */
    public function __construct(
        public array $fields,
    ) {
    }
}
