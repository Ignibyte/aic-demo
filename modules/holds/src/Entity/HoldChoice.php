<?php

declare(strict_types=1);

namespace App\Holds\Entity;

/**
 * One option of the hold form's selects (#20): its key, a VIN or an email, and its label.
 */
final readonly class HoldChoice
{
    /**
     * @param string $key   What the form sends.
     * @param string $label What it shows.
     */
    public function __construct(
        public string $key,
        public string $label,
    ) {
    }
}
