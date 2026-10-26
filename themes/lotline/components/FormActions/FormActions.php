<?php

declare(strict_types=1);

namespace App\Theme\Components\FormActions;

use AIC\Component\Contracts\Component;

/**
 * A form's buttons, from form-actions.phtml: submit, then a link that cancels.
 */
final readonly class FormActions implements Component
{
    /**
     * @param string $submit     The submit button's text.
     * @param string $id         The submit button's id.
     * @param string $cancelHref Where Cancel goes.
     */
    public function __construct(
        public string $submit,
        public string $id,
        public string $cancelHref,
    ) {
    }
}
