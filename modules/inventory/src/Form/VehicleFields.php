<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Component\Domain\KeyedComponent;
use App\Inventory\Service\VehicleStatuses;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\FormGrid\FormGrid;
use App\Theme\Components\SelectField\SelectField;
use App\Theme\Components\SelectField\SelectOption;
use InvalidArgumentException;

/**
 * The vehicle form's fields in the theme's grid, in `VehicleEntry::FIELDS`'s order: each with its value and its error,
 * `vehicle-<name>` its id, and the status a select.
 */
final readonly class VehicleFields
{
    /**
     * @param VehicleStatuses $vehicleStatuses The statuses the select offers.
     */
    public function __construct(
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
    ) {
    }

    /**
     * @param VehicleEntry          $vehicleEntry The values to show.
     * @param array<string, string> $errors       Why each refused field is refused, by name.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FormGrid The twelve fields.
     */
    public function grid(VehicleEntry $vehicleEntry, array $errors): FormGrid
    {
        return new FormGrid([
            $this->field('vin', 'VIN', 'text', $vehicleEntry, $errors),
            $this->field('year', 'Year', 'number', $vehicleEntry, $errors),
            $this->field('make', 'Make', 'text', $vehicleEntry, $errors),
            $this->field('model', 'Model', 'text', $vehicleEntry, $errors),
            $this->field('trim', 'Trim', 'text', $vehicleEntry, $errors),
            $this->field('body_style', 'Body style', 'text', $vehicleEntry, $errors),
            $this->field('exterior_colour', 'Exterior colour', 'text', $vehicleEntry, $errors),
            $this->field('mileage', 'Mileage', 'number', $vehicleEntry, $errors),
            $this->field('list_price', 'List price, in dollars', 'number', $vehicleEntry, $errors),
            $this->field('cost', 'Cost, in dollars', 'number', $vehicleEntry, $errors),
            new KeyedComponent('status', $this->status($vehicleEntry->value('status'), $errors['status'] ?? '')),
            $this->field('added_on', 'On the lot since', 'date', $vehicleEntry, $errors),
        ]);
    }

    /**
     * @param string                $name         The field's name.
     * @param string                $label        Its label.
     * @param string                $type         Its input type.
     * @param VehicleEntry          $vehicleEntry The values to show.
     * @param array<string, string> $errors       Why each refused field is refused, by name.
     *
     * @throws InvalidArgumentException When the name is no key.
     *
     * @return KeyedComponent The field, keyed by its name; never browser-required, so an empty post reaches the checks.
     */
    private function field(
        string $name,
        string $label,
        string $type,
        VehicleEntry $vehicleEntry,
        array $errors,
    ): KeyedComponent {
        $value = $vehicleEntry->value($name);
        $error = $errors[$name] ?? '';

        return new KeyedComponent($name, new FormField('vehicle-' . $name, $name, $label, $type, $value, $error, false, false));
    }

    /**
     * @param string $status The status to show chosen.
     * @param string $error  What is wrong with it; empty when nothing is.
     *
     * @return SelectField The status select.
     */
    private function status(string $status, string $error): SelectField
    {
        $options = [];

        foreach ($this->vehicleStatuses->all() as $vehicleStatus) {
            $options[] = new SelectOption($vehicleStatus->value, $vehicleStatus->label(), $vehicleStatus->value === $status);
        }

        return new SelectField('vehicle-status', 'status', 'Status', 'Choose a status', $options, $error, false);
    }
}
