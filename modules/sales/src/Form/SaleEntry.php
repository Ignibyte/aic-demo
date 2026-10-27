<?php

declare(strict_types=1);

namespace App\Sales\Form;

/**
 * The sale form's values as sent, by field name: what a post sent, or the form's start. The vehicle is a VIN, the
 * customer an email and the salesperson a login. Nothing in it is checked yet.
 */
final readonly class SaleEntry
{
    /**
     * The form's fields, in the order it shows them.
     */
    public const array FIELDS = ['vehicle', 'customer', 'salesperson', 'price', 'sold_on'];

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
