<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use AIC\Form\Application\EnvelopeCodec;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Service\VehicleStatuses;
use App\Staff\Form\StaffSeals;
use App\Theme\Components\EditRow\EditCell;
use App\Theme\Components\EditRow\EditField;
use App\Theme\Components\EditRow\EditRow;
use App\Theme\Components\SelectField\SelectOption;
use App\Theme\Components\TableRow\RowBox;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use LogicException;

use function implode;
use function in_array;
use function number_format;

/**
 * A vehicle's row in the inventory list (#28), shown or as a small form, one shape wherever it renders: keyed by its
 * VIN, anchored `vehicle-<VIN>`, its props sealing the vehicle's path. Shown, its price and status cells link to the
 * vehicle's edit page and, with script, ask for the row as a form; as a form, the list price is a text box and the
 * status a select, with Save and Cancel, and a refusal says why beside the field. Either way its VIN cell holds its box
 * (#29), which ticks it for the list's action bar, and it shows where on the lot the vehicle stands; a bulk edit's
 * answer shows a refused row set apart with its reason, and a deleted one in place, gone.
 */
final readonly class VehicleRowForm
{
    /**
     * The fields a row edits, as it sends them.
     */
    public const array FIELDS = ['list_price', 'status'];

    /**
     * What a row's anchor starts with; the VIN follows.
     */
    public const string ANCHOR = 'vehicle-';

    /**
     * The id of the inventory list's action bar's form (#29), which each row's box posts with.
     */
    public const string BULK = 'vehicles-bulk';

    /**
     * Where the action bar posts a bulk edit (#29).
     */
    public const string ACTION = Vehicle::PAGES . '/bulk';

    /**
     * The list a row's own answer shows the action bar on, which its plain post goes back to.
     */
    public const string LIST = Vehicle::PAGES;

    /**
     * Seals the vehicle's path as the row's props.
     */
    private StaffSeals $staffSeals;

    /**
     * @param EnvelopeCodec   $envelopeCodec   Seals with the app secret; the row's seal is built on it, so the list's
     *                                         wiring shares no seal with the record's services (gate:14).
     * @param VehicleStatuses $vehicleStatuses The statuses and their badges' colours.
     */
    public function __construct(
        EnvelopeCodec $envelopeCodec,
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
    ) {
        $this->staffSeals = new StaffSeals($envelopeCodec);
    }

    /**
     * @param Vehicle $vehicle A vehicle.
     *
     * @throws LogicException When the VIN does not fit a key, which a stored VIN always does; or its path cannot be sealed.
     *
     * @return KeyedComponent Its row: its box and the VIN, linked to its page and prefetching it on hover (#43), what it
     *                        is, its mileage, its price and its status as a badge, both editable, its location and the
     *                        day it came.
     */
    public function shown(Vehicle $vehicle): KeyedComponent
    {
        return $this->ticked($vehicle, false, '');
    }

    /**
     * @param Vehicle $vehicle A vehicle a bulk edit was posted for (#29).
     * @param bool    $checked Whether its box is ticked as it arrives.
     * @param string  $refusal Why the bulk edit was refused for it; empty when it was not.
     *
     * @throws LogicException When the VIN does not fit a key, which a stored VIN always does; or its path cannot be sealed.
     *
     * @return KeyedComponent Its row shown, its box as given; a refused row's box marked, its reason under its VIN.
     */
    public function ticked(Vehicle $vehicle, bool $checked, string $refusal): KeyedComponent
    {
        $status = $vehicle->status;
        $page = $vehicle->path() . '/edit';
        $form = $vehicle->path() . '/row/edit';
        $box = $this->box($vehicle, $checked, $refusal);

        return $this->keyed($vehicle, new TableRow([
            new TableCell($vehicle->vin, $vehicle->path(), true, false, null, '', [$box], 'hover'),
            new TableCell((string) $vehicle->year, '', false, false, null),
            new TableCell($vehicle->make, '', false, false, null),
            new TableCell($vehicle->model . ' ' . $vehicle->trim, '', false, false, null),
            new TableCell($vehicle->bodyStyle, '', false, false, null),
            new TableCell($vehicle->exteriorColour, '', false, false, null),
            new TableCell(number_format($vehicle->mileage) . ' mi', '', false, true, null),
            new TableCell('$' . number_format($vehicle->price->list), $page, false, true, null, $form),
            new TableCell($status->label(), $page, false, false, $this->vehicleStatuses->tone($status), $form),
            new TableCell($vehicle->lot->place(), '', false, false, null),
            new TableCell($vehicle->lot->addedOn, '', false, false, null),
        ]));
    }

    /**
     * @param string $vin The VIN of a ticked row a bulk edit found no vehicle for (#29).
     *
     * @throws LogicException When the VIN does not fit a key.
     *
     * @return KeyedComponent Its row in place, under its key and anchor: its VIN and "No longer on the lot" as text, no box
     *                        and no links, Deleted in its status cell, the row set apart; the list's next read leaves it
     *                        out. A vehicle the edit deletes itself answers no row and leaves the page (AIC #247).
     */
    public function gone(string $vin): KeyedComponent
    {
        $tableCell = new TableCell('', '', false, false, null);

        return new KeyedComponent($vin, new TableRow([
            new TableCell($vin, '', true, false, null),
            new TableCell('No longer on the lot', '', false, false, null),
            $tableCell,
            $tableCell,
            $tableCell,
            $tableCell,
            $tableCell,
            $tableCell,
            new TableCell('Deleted', '', false, false, null),
            $tableCell,
            $tableCell,
        ], true), '', self::ANCHOR . $vin);
    }

    /**
     * @param Vehicle               $vehicle      The vehicle as stored.
     * @param VehicleEntry          $vehicleEntry What the fields hold: the stored values, or what the row sent.
     * @param array<string, string> $errors       Why each refused field was refused, by name; empty for none.
     *
     * @throws LogicException When the VIN does not fit a key, which a stored VIN always does; or its path cannot be sealed.
     *
     * @return KeyedComponent Its row as a form, under the same key, anchor and props as the row shown.
     */
    public function editing(Vehicle $vehicle, VehicleEntry $vehicleEntry, array $errors): KeyedComponent
    {
        $anchor = self::ANCHOR . $vehicle->vin;
        $listPrice = $vehicleEntry->value('list_price');
        $priceError = $errors['list_price'] ?? '';
        $price = new EditField('list_price-' . $anchor, 'list_price', 'List price', 'text', $listPrice, [], $priceError, true);
        $options = $this->options($vehicleEntry->value('status'));
        $status = new EditField('status-' . $anchor, 'status', 'Status', 'select', '', $options, $errors['status'] ?? '', false);
        $row = $vehicle->path() . '/row';

        return $this->keyed($vehicle, new EditRow('form-' . $anchor, $row, $row, [
            new EditCell($vehicle->vin, true, false, [], false, [$this->box($vehicle, false, '')]),
            new EditCell((string) $vehicle->year, false, false, [], false),
            new EditCell($vehicle->make, false, false, [], false),
            new EditCell($vehicle->model . ' ' . $vehicle->trim, false, false, [], false),
            new EditCell($vehicle->bodyStyle, false, false, [], false),
            new EditCell($vehicle->exteriorColour, false, false, [], false),
            new EditCell(number_format($vehicle->mileage) . ' mi', false, true, [], false),
            new EditCell('', false, true, [$price], false),
            new EditCell('', false, false, [$status], true),
            new EditCell($vehicle->lot->place(), false, false, [], false),
            new EditCell($vehicle->lot->addedOn, false, false, [], false),
        ], $this->others($errors)));
    }

    /**
     * @param Vehicle   $vehicle   The vehicle.
     * @param Component $component Its row, shown or as a form.
     *
     * @throws LogicException When the VIN does not fit a key; or its path cannot be sealed.
     *
     * @return KeyedComponent The row under the vehicle's key, anchor and props.
     */
    private function keyed(Vehicle $vehicle, Component $component): KeyedComponent
    {
        return new KeyedComponent($vehicle->vin, $component, $this->staffSeals->props($vehicle->path()), self::ANCHOR . $vehicle->vin);
    }

    /**
     * @param Vehicle $vehicle A vehicle.
     * @param bool    $checked Whether its box is ticked as it arrives.
     * @param string  $refusal Why a bulk edit was refused for it; empty for none.
     *
     * @return RowBox Its box (#29), which posts its VIN with the inventory list's action bar.
     */
    private function box(Vehicle $vehicle, bool $checked, string $refusal): RowBox
    {
        return new RowBox(self::BULK, 'vins[]', $vehicle->vin, 'Select the ' . $vehicle->title(), $checked, $refusal);
    }

    /**
     * @param string $chosen The status the field holds.
     *
     * @return list<SelectOption> Every status, the chosen one selected.
     */
    private function options(string $chosen): array
    {
        $options = [];

        foreach ($this->vehicleStatuses->all() as $vehicleStatus) {
            $options[] = new SelectOption($vehicleStatus->value, $vehicleStatus->label(), $vehicleStatus->value === $chosen);
        }

        return $options;
    }

    /**
     * @param array<string, string> $errors Why each refused field was refused, by name.
     *
     * @return string Why the row was refused for a stored value it shows no field for; empty for none.
     */
    private function others(array $errors): string
    {
        $others = [];

        foreach ($errors as $name => $error) {
            if (in_array($name, self::FIELDS, true)) {
                continue;
            }

            $others[] = $error;
        }

        return $others === []
            ? ''
            : 'Open the full form to save this vehicle: ' . implode(' ', $others);
    }
}
