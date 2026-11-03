<?php

declare(strict_types=1);

namespace App\Settings\Entity;

use App\Finance\Service\Payments;

use function implode;

/**
 * The dealership's profile (#22): its name, which the public header and the mails show; its address and phone, which
 * the public footer shows; its opening hours, which a test drive is held to; the days on the lot after which a
 * vehicle is aged stock; and the default APR the payment calculator starts at (#26). Until an admin saves one, every
 * reader gets `ProfileRepository`'s defaults, the values the app had in code and config before, so nothing changes
 * until a save.
 */
final readonly class DealershipProfile
{
    /**
     * The form's fields, in the order it shows them; each is a column of `dealership_profile`, the APR as
     * `default_apr_bp`.
     */
    public const array FIELDS = ['name', 'address', 'phone', 'opens_at', 'closes_at', 'open_days', 'aged_stock_days', 'default_apr'];

    /**
     * @param string       $name           The dealership's name.
     * @param string       $address        Its address; empty for none.
     * @param string       $phone          Its phone; empty for none.
     * @param OpeningHours $openingHours   When it is open.
     * @param int          $agedStockDays  The days on the lot after which an unsold vehicle is aged stock.
     * @param int          $aprBasisPoints The default APR for the payment calculator, in basis points (690 is 6.90%).
     */
    public function __construct(
        public string $name,
        public string $address,
        public string $phone,
        public OpeningHours $openingHours,
        public int $agedStockDays,
        public int $aprBasisPoints,
    ) {
    }

    /**
     * @return array<string, string> Each field's value as the form shows it and an envelope seals it, by name.
     */
    public function values(): array
    {
        return [
            'address' => $this->address,
            'aged_stock_days' => (string) $this->agedStockDays,
            'closes_at' => $this->openingHours->closesAt,
            'default_apr' => $this->apr(),
            'name' => $this->name,
            'opens_at' => $this->openingHours->opensAt,
            'open_days' => $this->openingHours->openDays,
            'phone' => $this->phone,
        ];
    }

    /**
     * @return array<string, string> Each field's value as a person reads it, by name: the open days in words.
     */
    public function shown(): array
    {
        $values = $this->values();
        $values['open_days'] = $this->openingHours->days();

        return $values;
    }

    /**
     * @return string The public footer's facts: the address, the phone and the hours, those given, joined by ` · `.
     */
    public function facts(): string
    {
        $facts = [];

        foreach ([$this->address, $this->phone, $this->openingHours->line()] as $fact) {
            if ($fact === '') {
                continue;
            }

            $facts[] = $fact;
        }

        return implode(' · ', $facts);
    }

    /**
     * @return string The default APR as the form shows it: a percentage with two decimals, such as `6.90`.
     */
    private function apr(): string
    {
        return (new Payments())->percent($this->aprBasisPoints);
    }
}
