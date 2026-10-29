<?php

declare(strict_types=1);

namespace App\Holds\Form;

use App\Holds\Entity\HoldDetails;

/**
 * What the hold form's checks found (#20): each refused field's message, or, when nothing was refused, the hold to
 * place.
 */
final readonly class HoldCheck
{
    /**
     * @param array<string, string> $errors      Why each refused field is refused, by name; empty when the entry is valid.
     * @param HoldDetails|null      $holdDetails The hold the valid entry describes; null when something was refused.
     */
    public function __construct(
        public array $errors,
        public ?HoldDetails $holdDetails,
    ) {
    }
}
