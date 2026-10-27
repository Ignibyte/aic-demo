<?php

declare(strict_types=1);

namespace App\Customers\Form;

/**
 * The customer form's values as typed, by field name: what a post sent, what a stored customer fills the form with,
 * or the blank form's start. Nothing in it is checked yet.
 */
final readonly class CustomerEntry
{
    /**
     * The form's fields, in the order it shows them; each is a column of `customers`.
     */
    public const array FIELDS = ['name', 'email', 'phone', 'address', 'notes'];

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
