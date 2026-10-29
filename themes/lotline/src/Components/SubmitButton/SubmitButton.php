<?php

declare(strict_types=1);

namespace App\Theme\Components\SubmitButton;

use AIC\Component\Contracts\Component;

/**
 * A form's submit button (#18). With a target (`@car-inquiry`), Tessera sends the submit to that anchored component
 * and swaps it alone, since it reads a submit's target from the button before the form; without one, the form's
 * region is the target, as for every other form.
 */
final readonly class SubmitButton implements Component
{
    /**
     * @param string $label  What it says.
     * @param string $id     Its id.
     * @param string $target The Tessera target its submit swaps, such as `@car-inquiry`; empty for the form's region.
     */
    public function __construct(
        public string $label,
        public string $id,
        public string $target,
    ) {
    }
}
