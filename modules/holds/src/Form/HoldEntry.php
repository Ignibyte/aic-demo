<?php

declare(strict_types=1);

namespace App\Holds\Form;

/**
 * The hold form's values as sent, by field name (#20): the vehicle is a VIN, the customer an email. Nothing in it is
 * checked yet.
 */
final readonly class HoldEntry
{
    /**
     * The form's fields, in the order it shows them.
     */
    public const array FIELDS = ['vehicle', 'customer', 'note'];

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
