<?php

declare(strict_types=1);

namespace App\Theme\Components\FormValue;

use AIC\Component\Contracts\Component;

/**
 * A hidden value a form sends from outside it, from form-value.phtml: tied to the form by its id, so it can sit in a
 * part of the page that is swapped without the form.
 */
final readonly class FormValue implements Component
{
    /**
     * @param string $form  The id of the form that sends it.
     * @param string $name  The field's name.
     * @param string $value Its value, written even when empty.
     */
    public function __construct(
        public string $form,
        public string $name,
        public string $value,
    ) {
    }
}
