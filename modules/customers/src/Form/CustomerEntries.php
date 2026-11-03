<?php

declare(strict_types=1);

namespace App\Customers\Form;

use AIC\Form\Application\PostedFields;
use App\Customers\Entity\Customer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fills the customer form: from a post, each field as sent (a missing one or an array is empty); from a stored
 * customer; from a stored customer with a list row's phone as sent (#28); or blank.
 */
final readonly class CustomerEntries
{
    /**
     * @param PostedFields $postedFields Reads the posted fields.
     */
    public function __construct(
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted customer form.
     *
     * @return CustomerEntry What it sent.
     */
    public function posted(ServerRequestInterface $serverRequest): CustomerEntry
    {
        $values = [];

        foreach (CustomerEntry::FIELDS as $field) {
            $values[$field] = $this->postedFields->value($serverRequest, $field);
        }

        return new CustomerEntry($values);
    }

    /**
     * @param Customer $customer A stored customer.
     *
     * @return CustomerEntry Its details, as the edit form starts.
     */
    public function filled(Customer $customer): CustomerEntry
    {
        $customerDetails = $customer->customerDetails;

        return new CustomerEntry([
            'address' => $customerDetails->address,
            'email' => $customerDetails->email,
            'name' => $customerDetails->name,
            'notes' => $customerDetails->notes,
            'phone' => $customerDetails->phone,
        ]);
    }

    /**
     * @param Customer               $customer      The stored customer.
     * @param ServerRequestInterface $serverRequest A list row's save (#28), which sends the phone.
     *
     * @return CustomerEntry The stored customer's details, with the phone as the row sent it.
     */
    public function row(Customer $customer, ServerRequestInterface $serverRequest): CustomerEntry
    {
        $values = $this->filled($customer)->values;
        $values['phone'] = $this->postedFields->value($serverRequest, 'phone');

        return new CustomerEntry($values);
    }

    /**
     * @return CustomerEntry The new form's start: every field empty.
     */
    public function blank(): CustomerEntry
    {
        return new CustomerEntry([]);
    }
}
