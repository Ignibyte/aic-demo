<?php

declare(strict_types=1);

namespace App\Customers\Form;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use App\Customers\Entity\Customer;
use App\Staff\Form\StaffSeals;
use App\Theme\Components\EditRow\EditCell;
use App\Theme\Components\EditRow\EditField;
use App\Theme\Components\EditRow\EditRow;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use LogicException;

use function implode;

/**
 * A customer's row in the customers list (#28), shown or as a small form, one shape wherever it renders: keyed and
 * anchored `customer-<id>`, its props sealing the customer's path. Shown, its phone cell links to the customer's edit
 * page and, with script, asks for the row as a form; as a form, the phone is a text box with Save and Cancel, and a
 * refusal says why beside it.
 */
final readonly class CustomerRowForm
{
    /**
     * What a row's key and anchor start with; the id follows.
     */
    public const string ANCHOR = 'customer-';

    /**
     * @param StaffSeals $staffSeals Seals the customer's path as the row's props.
     */
    public function __construct(
        private StaffSeals $staffSeals,
    ) {
    }

    /**
     * @param Customer $customer A customer.
     *
     * @throws LogicException When a key does not fit, which an id's always does; or its path cannot be sealed.
     *
     * @return KeyedComponent Its row: the name, linked to its page, the email, the phone, editable, and the day it was
     *                        added.
     */
    public function shown(Customer $customer): KeyedComponent
    {
        $customerDetails = $customer->customerDetails;
        $phone = $customerDetails->phone === ''
            ? 'Add a phone'
            : $customerDetails->phone;

        return $this->keyed($customer, new TableRow([
            new TableCell($customerDetails->name, $customer->path(), false, false, null),
            new TableCell($customerDetails->email, '', false, false, null),
            new TableCell($phone, $customer->path() . '/edit', false, false, null, $customer->path() . '/row/edit'),
            new TableCell($customer->addedOn, '', false, false, null),
        ]));
    }

    /**
     * @param Customer              $customer      The customer as stored.
     * @param CustomerEntry         $customerEntry What the field holds: the stored phone, or what the row sent.
     * @param array<string, string> $errors        Why each refused field was refused, by name; empty for none.
     *
     * @throws LogicException When a key does not fit; or its path cannot be sealed.
     *
     * @return KeyedComponent Its row as a form, under the same key, anchor and props as the row shown.
     */
    public function editing(Customer $customer, CustomerEntry $customerEntry, array $errors): KeyedComponent
    {
        $anchor = self::ANCHOR . $customer->id;
        $phoneError = $errors['phone'] ?? '';
        unset($errors['phone']);
        $editField = new EditField('phone-' . $anchor, 'phone', 'Phone', 'tel', $customerEntry->value('phone'), [], $phoneError, true);
        $others = $errors === []
            ? ''
            : 'Open the full form to save this customer: ' . implode(' ', $errors);
        $row = $customer->path() . '/row';

        return $this->keyed($customer, new EditRow('form-' . $anchor, $row, $row, [
            new EditCell($customer->customerDetails->name, false, false, [], false),
            new EditCell($customer->customerDetails->email, false, false, [], false),
            new EditCell('', false, false, [$editField], true),
            new EditCell($customer->addedOn, false, false, [], false),
        ], $others));
    }

    /**
     * @param Customer  $customer  The customer.
     * @param Component $component Its row, shown or as a form.
     *
     * @throws LogicException When a key does not fit; or its path cannot be sealed.
     *
     * @return KeyedComponent The row under the customer's key, its anchor and its props.
     */
    private function keyed(Customer $customer, Component $component): KeyedComponent
    {
        $name = self::ANCHOR . $customer->id;

        return new KeyedComponent($name, $component, $this->staffSeals->props($customer->path()), $name);
    }
}
