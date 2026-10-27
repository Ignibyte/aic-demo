<?php

declare(strict_types=1);

namespace App\Customers\Form;

use AIC\Component\Domain\KeyedComponent;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\FormGrid\FormGrid;
use InvalidArgumentException;

/**
 * The customer form's fields in the theme's grid, in `CustomerEntry::FIELDS`'s order: each with its value and its
 * error, `customer-<name>` its id, the address and the notes textareas.
 */
final readonly class CustomerFields
{
    /**
     * @param CustomerEntry         $customerEntry The values to show.
     * @param array<string, string> $errors        Why each refused field is refused, by name.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FormGrid The five fields.
     */
    public function grid(CustomerEntry $customerEntry, array $errors): FormGrid
    {
        return new FormGrid([
            $this->control($customerEntry, $errors, 'name', 'Name', 'text'),
            $this->control($customerEntry, $errors, 'email', 'Email', 'email'),
            $this->control($customerEntry, $errors, 'phone', 'Phone (optional)', 'tel'),
            $this->control($customerEntry, $errors, 'address', 'Address', 'textarea'),
            $this->control($customerEntry, $errors, 'notes', 'Notes', 'textarea'),
        ]);
    }

    /**
     * @param CustomerEntry         $customerEntry The values to show.
     * @param array<string, string> $errors        Why each refused field is refused, by name.
     * @param string                $field         The field's name.
     * @param string                $label         Its label.
     * @param string                $type          Its control's type.
     *
     * @throws InvalidArgumentException When the name is no key.
     *
     * @return KeyedComponent The field, keyed by its name; never browser-required, so an empty post reaches the checks.
     */
    private function control(
        CustomerEntry $customerEntry,
        array $errors,
        string $field,
        string $label,
        string $type,
    ): KeyedComponent {
        $value = $customerEntry->value($field);
        $formField = new FormField('customer-' . $field, $field, $label, $type, $value, $errors[$field] ?? '', false, false);

        return new KeyedComponent($field, $formField);
    }
}
