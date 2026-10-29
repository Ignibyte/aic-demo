<?php

declare(strict_types=1);

namespace App\Holds\Form;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Holds\Entity\HoldChoice;
use App\Holds\Repository\HoldChoiceRepository;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\FormGrid\FormGrid;
use App\Theme\Components\SelectField\SelectField;
use App\Theme\Components\SelectField\SelectOption;
use InvalidArgumentException;

use function strtoupper;
use function trim;

/**
 * The hold form's fields in the theme's grid (#20), each `hold-<name>` its id: the vehicle in stock and the customer
 * chosen from the database's options, then the note. None is browser-required, so an empty post reaches the checks.
 */
final readonly class HoldFields
{
    /**
     * @param HoldChoiceRepository $holdChoiceRepository The options.
     */
    public function __construct(
        private HoldChoiceRepository $holdChoiceRepository,
    ) {
    }

    /**
     * @param HoldEntry             $holdEntry The values to show.
     * @param array<string, string> $errors    Why each refused field is refused, by name.
     *
     * @throws QueryException           When the options cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FormGrid The three fields.
     */
    public function grid(HoldEntry $holdEntry, array $errors): FormGrid
    {
        $vin = strtoupper(trim($holdEntry->value('vehicle')));
        $customer = trim($holdEntry->value('customer'));
        $vehicles = $this->holdChoiceRepository->vehicles();
        $customers = $this->holdChoiceRepository->customers();
        $formField = new FormField('hold-note', 'note', 'Note', 'textarea', $holdEntry->value('note'), $errors['note'] ?? '', false, false);

        return new FormGrid([
            $this->select('vehicle', 'Vehicle', 'Choose a vehicle in stock', $vehicles, $vin, $errors),
            $this->select('customer', 'Customer', 'Choose a customer', $customers, $customer, $errors),
            new KeyedComponent('note', $formField),
        ]);
    }

    /**
     * @param string                $name    The field's name.
     * @param string                $label   Its label.
     * @param string                $prompt  The empty option's text.
     * @param list<HoldChoice>      $choices What it offers.
     * @param string                $chosen  The chosen key.
     * @param array<string, string> $errors  Why each refused field is refused, by name.
     *
     * @throws InvalidArgumentException When the name is no key.
     *
     * @return KeyedComponent The select, keyed by its name.
     */
    private function select(
        string $name,
        string $label,
        string $prompt,
        array $choices,
        string $chosen,
        array $errors,
    ): KeyedComponent {
        $options = [];

        foreach ($choices as $choice) {
            $options[] = new SelectOption($choice->key, $choice->label, $choice->key === $chosen);
        }

        return new KeyedComponent($name, new SelectField('hold-' . $name, $name, $label, $prompt, $options, $errors[$name] ?? '', false));
    }
}
