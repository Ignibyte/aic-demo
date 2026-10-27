<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\KeyedComponent;
use App\Inventory\Form\InventoryQuery;
use App\Theme\Components\FilterBar\FilterBar;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\SelectField\SelectField;
use App\Theme\Components\SelectField\SelectOption;
use InvalidArgumentException;

/**
 * The inventory list's filter bar: the search, the make and status selects and the price range, each showing what was
 * asked, with the sort kept in a hidden field. It is a plain GET form, which Tessera answers in `content`.
 */
final readonly class InventoryFilters
{
    /**
     * @param VehicleStatuses $vehicleStatuses The statuses to offer.
     * @param InventoryLinks  $inventoryLinks  Writes the kept sort.
     */
    public function __construct(
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
        private InventoryLinks $inventoryLinks = new InventoryLinks(),
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
        $sort = $this->inventoryLinks->sortValue($inventoryQuery->sort, $inventoryQuery->descending);

        return new FilterBar('inventory-filters', InventoryLinks::PATH, 'content', [
            new KeyedComponent('q', $formField),
            new KeyedComponent('make', $make),
            new KeyedComponent('status', $status),
            new KeyedComponent('min_price', $this->price('min-price', 'min_price', 'Price from', $inventoryQuery->minPrice)),
            new KeyedComponent('max_price', $this->price('max-price', 'max_price', 'Price to', $inventoryQuery->maxPrice)),
            new KeyedComponent('sort', new HiddenInput('sort', $sort)),
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
