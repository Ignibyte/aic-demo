<?php

declare(strict_types=1);

namespace App\Settings\Form;

/**
 * The profile form's values as typed, by field name (`DealershipProfile::FIELDS`): what a post sent, or what the stored
 * profile fills the form with. The open days are ISO weekday digits, gathered from the seven boxes. Nothing in it is
 * checked yet.
 */
final readonly class ProfileEntry
{
    /**
     * @param array<string, string> $values Each field's value, by name.
     */
    public function __construct(
        public array $values,
    ) {
    }

    /**
     * @param string $name A field's name.
     *
     * @return string Its value; empty when it has none.
     */
    public function value(string $name): string
    {
        return $this->values[$name] ?? '';
    }
}
