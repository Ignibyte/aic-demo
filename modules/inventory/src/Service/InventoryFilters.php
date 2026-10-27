<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Inventory\Form\InventoryQuery;
use App\Theme\Components\FilterBar\FilterBar;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\SelectField\SelectField;
use App\Theme\Components\SelectField\SelectOption;
use InvalidArgumentException;

/**
 * The inventory list's filter bar: the search, the make and status selects and the price range, each showing what was
 * asked. It is a plain GET form, which Tessera answers with the results alone; the sort it sends sits in the results
 * (`InventoryTable`), tied to the form by its id, so a sort that swaps only them keeps it current.
 */
final readonly class InventoryFilters
{
    /**
     * The form's id.
     */
    public const string FORM = 'inventory-filters';

    /**
     * @param VehicleStatuses $vehicleStatuses The statuses to offer.
     */
    public function __construct(
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
    ) {
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     * @param list<string>   $makes          The makes on the lot.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FilterBar The filter bar.
     */
    public function filterBar(InventoryQuery $inventoryQuery, array $makes): FilterBar
    {
        $formField = new FormField('filter-q', 'q', 'Search', 'search', $inventoryQuery->search, '', false, true);
        $make = new SelectField('filter-make', 'make', 'Make', 'Every make', $this->makes($inventoryQuery, $makes), '', true);
        $status = new SelectField('filter-status', 'status', 'Status', 'Every status', $this->statuses($inventoryQuery), '', true);

        return new FilterBar(self::FORM, InventoryLinks::PATH, InventoryTable::TARGET, [
            new KeyedComponent('q', $formField),
            new KeyedComponent('make', $make),
            new KeyedComponent('status', $status),
            new KeyedComponent('min_price', $this->price('min-price', 'min_price', 'Price from', $inventoryQuery->minPrice)),
            new KeyedComponent('max_price', $this->price('max-price', 'max_price', 'Price to', $inventoryQuery->maxPrice)),
        ], InventoryLinks::PATH);
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     * @param list<string>   $makes          The makes on the lot.
     *
     * @return list<SelectOption> A make each, the asked one chosen.
     */
    private function makes(InventoryQuery $inventoryQuery, array $makes): array
    {
        $options = [];

        foreach ($makes as $make) {
            $options[] = new SelectOption($make, $make, $make === $inventoryQuery->make);
        }

        return $options;
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     *
     * @return list<SelectOption> A status each, the asked one chosen.
     */
    private function statuses(InventoryQuery $inventoryQuery): array
    {
        $options = [];

        foreach ($this->vehicleStatuses->all() as $vehicleStatus) {
            $value = $vehicleStatus->value;
            $options[] = new SelectOption($value, $vehicleStatus->label(), $value === $inventoryQuery->status);
        }

        return $options;
    }

    /**
     * @param string   $id    The control's id, after `filter-`.
     * @param string   $name  The parameter.
     * @param string   $label The label.
     * @param int|null $price The bound asked for; null for none.
     *
     * @return FormField The compact number field.
     */
    private function price(string $id, string $name, string $label, ?int $price): FormField
    {
        return new FormField('filter-' . $id, $name, $label, 'number', $price === null ? '' : (string) $price, '', false, true);
    }
}
