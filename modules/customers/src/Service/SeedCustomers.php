<?php

declare(strict_types=1);

namespace App\Customers\Service;

use App\Customers\Entity\CustomerDetails;

use function strtolower;

/**
 * The 40 invented customers `customers:seed` saves. Everything follows from the customer's place by arithmetic over
 * fixed lists, so every run makes the same 40: each first name twice, with two different last names, emails at
 * `example.test`, phones in the fictional 555-0100 to 555-0199 range and addresses in invented Ridgeway.
 */
final readonly class SeedCustomers
{
    /**
     * How many customers the seed makes.
     */
    private const int COUNT = 40;

    /**
     * The first names, each used twice.
     */
    private const array FIRST_NAMES = [
        'Ada', 'Ben', 'Carmen', 'Dev', 'Elena', 'Farid', 'Grace', 'Hiro', 'Imani', 'Jonah',
        'Kira', 'Luis', 'Maya', 'Nils', 'Olu', 'Petra', 'Quinn', 'Rosa', 'Sami', 'Tess',
    ];

    /**
     * The last names; a first name's two customers take two different ones.
     */
    private const array LAST_NAMES = [
        'Abbott', 'Brennan', 'Castillo', 'Duarte', 'Ellison', 'Fischer', 'Garza', 'Holm', 'Ibarra', 'Jensen',
        'Kowalski', 'Lindqvist', 'Moreno', 'Nakamura', 'Okafor', 'Pereira', 'Quintero', 'Reyes', 'Sato', 'Tran',
    ];

    /**
     * The streets of invented Ridgeway.
     */
    private const array STREETS = [
        'Alder Street', 'Birch Lane', 'Cedar Avenue', 'Dogwood Court', 'Elm Road', 'Fir Way', 'Juniper Drive', 'Maple Terrace',
    ];

    /**
     * The notes every fourth customer carries, in turn.
     */
    private const array NOTES = [
        'Prefers a call after 5pm.',
        'Has a 2015 sedan to trade in.',
        'Asked about financing.',
        'Referred by a past customer.',
        'Wants a test drive on a weekend.',
    ];

    /**
     * @return list<CustomerDetails> The 40 customers, the same on every call.
     */
    public function all(): array
    {
        $customers = [];

        for ($index = 0; $index < self::COUNT; $index += 1) {
            $customers[] = $this->customer($index);
        }

        return $customers;
    }

    /**
     * @param int<0, max> $index The customer's place, 0 to 39.
     *
     * @return CustomerDetails The customer there: the first name by the place, the last name three steps on for each,
     *                         and seven more for the second customer with that first name; every fourth has notes,
     *                         picked by the first name's place.
     */
    private function customer(int $index): CustomerDetails
    {
        $first = $index % 20;
        $firstName = self::FIRST_NAMES[$first];
        $lastName = self::LAST_NAMES[($first * 3 + ($index < 20 ? 0 : 7)) % 20];
        $street = self::STREETS[$index % 8];
        $notes = $index % 4 === 0
            ? self::NOTES[$first % 5]
            : '';

        return new CustomerDetails(
            $firstName . ' ' . $lastName,
            strtolower($firstName . '.' . $lastName) . '@example.test',
            '(503) 555-01' . ($index < 10 ? '0' : '') . $index,
            (100 + ($index * 137) % 9_000) . ' ' . $street . ', Ridgeway',
            $notes,
        );
    }
}
