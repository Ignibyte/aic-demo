<?php

declare(strict_types=1);

namespace App\Sales\Form;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Sales\Entity\Choice;
use App\Sales\Repository\SaleChoiceRepository;
use App\Sales\Service\SaleOptions;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\FormGrid\FormGrid;
use App\Theme\Components\SelectField\SelectField;
use InvalidArgumentException;

use function strtoupper;
use function trim;

/**
 * The sale form's fields in the theme's grid, in `SaleEntry::FIELDS`'s order, each `sale-<name>` its id: the vehicle,
 * the customer and the salesperson chosen from the database's options, then the price and the day. None is
 * browser-required, so an empty post reaches the checks.
 */
final readonly class SaleFields
{
    /**
     * @param SaleChoiceRepository $saleChoiceRepository The options.
     * @param SaleOptions          $saleOptions          Turns them into the select's options.
     */
    public function __construct(
        private SaleChoiceRepository $saleChoiceRepository,
        private SaleOptions $saleOptions = new SaleOptions(),
    ) {
    }

    /**
     * @param SaleEntry             $saleEntry The values to show.
     * @param array<string, string> $errors    Why each refused field is refused, by name.
     *
     * @throws QueryException           When the options cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FormGrid The five fields.
     */
    public function grid(SaleEntry $saleEntry, array $errors): FormGrid
    {
        $vin = strtoupper(trim($saleEntry->value('vehicle')));
        $vehicles = $this->saleChoiceRepository->vehicles($vin);
        $customers = $this->saleChoiceRepository->customers();
        $staff = $this->saleChoiceRepository->staff();
        $customer = trim($saleEntry->value('customer'));
        $salesperson = trim($saleEntry->value('salesperson'));

        return new FormGrid([
            $this->select('vehicle', 'Vehicle', 'Choose a vehicle', $vehicles, $vin, $errors),
            $this->select('customer', 'Customer', 'Choose a customer', $customers, $customer, $errors),
            $this->select('salesperson', 'Salesperson', 'Choose a member of staff', $staff, $salesperson, $errors),
            $this->field($saleEntry, $errors, 'price', 'Sale price (whole dollars)', 'number'),
            $this->field($saleEntry, $errors, 'sold_on', 'Sold on', 'date'),
        ]);
    }

    /**
     * @param string                $name    The field's name.
     * @param string                $label   Its label.
     * @param string                $prompt  The empty option's text.
     * @param list<Choice>          $choices What it offers.
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
        $options = $this->saleOptions->options($choices, $chosen);

        return new KeyedComponent($name, new SelectField('sale-' . $name, $name, $label, $prompt, $options, $errors[$name] ?? '', false));
    }

    /**
     * @param SaleEntry             $saleEntry The values to show.
     * @param array<string, string> $errors    Why each refused field is refused, by name.
     * @param string                $name      The field's name.
     * @param string                $label     Its label.
     * @param string                $type      Its control's type.
     *
     * @throws InvalidArgumentException When the name is no key.
     *
     * @return KeyedComponent The field, keyed by its name.
     */
    private function field(
        SaleEntry $saleEntry,
        array $errors,
        string $name,
        string $label,
        string $type,
    ): KeyedComponent {
        $formField = new FormField('sale-' . $name, $name, $label, $type, $saleEntry->value($name), $errors[$name] ?? '', false, false);

        return new KeyedComponent($name, $formField);
    }
}
