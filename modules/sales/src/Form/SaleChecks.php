<?php

declare(strict_types=1);

namespace App\Sales\Form;

use AIC\Database\Contracts\QueryException;
use App\Holds\Repository\HoldRepository;
use App\Sales\Entity\SaleDetails;
use App\Sales\Entity\SaleVehicle;
use App\Sales\Repository\SaleChoiceRepository;
use InvalidArgumentException;

use function checkdate;
use function preg_match;
use function strcmp;
use function strtoupper;
use function substr;
use function trim;

/**
 * The sale form's checks, field by field: a vehicle on the lot that is not sold, nor held for another customer (#20), a
 * customer, a member of staff, a price of whole dollars from 1 to 9,999,999, and a real day that is neither after today
 * nor before the vehicle came; and the sale a valid entry describes, by the ids of what it names.
 */
final readonly class SaleChecks
{
    /**
     * Why a sold vehicle is refused.
     */
    public const string ALREADY_SOLD = 'This vehicle is already sold.';

    /**
     * Why a vehicle held for another customer is refused.
     */
    public const string HELD = 'This vehicle is on hold for another customer.';

    /**
     * @param SaleChoiceRepository $saleChoiceRepository Finds what the entry names.
     * @param HoldRepository       $holdRepository       Whether a vehicle is held for another customer, holds'.
     */
    public function __construct(
        private SaleChoiceRepository $saleChoiceRepository,
        private HoldRepository $holdRepository,
    ) {
    }

    /**
     * Looks up each key even when it is empty, which finds nothing, so an empty field needs no branch of its own.
     *
     * @param SaleEntry $saleEntry What was posted.
     * @param string    $today     The database's today, `YYYY-MM-DD`.
     *
     * @throws QueryException           When what the entry names cannot be read.
     * @throws InvalidArgumentException When an entity type it goes through is malformed.
     *
     * @return SaleCheck Each refused field's message, or the sale to record.
     */
    public function check(SaleEntry $saleEntry, string $today): SaleCheck
    {
        $vin = strtoupper(trim($saleEntry->value('vehicle')));
        $saleVehicle = $this->saleChoiceRepository->vehicle($vin);
        $customerId = $this->saleChoiceRepository->customerId(trim($saleEntry->value('customer')));
        $salespersonId = $this->saleChoiceRepository->memberId(trim($saleEntry->value('salesperson')));
        $price = trim($saleEntry->value('price'));
        $soldOn = trim($saleEntry->value('sold_on'));
        $errors = $this->refused([
            'customer' => $this->choiceError($saleEntry->value('customer'), $customerId, 'customer'),
            'price' => $this->priceError($price),
            'salesperson' => $this->choiceError($saleEntry->value('salesperson'), $salespersonId, 'salesperson'),
            'sold_on' => $this->dayError($soldOn, $today, $saleVehicle),
            'vehicle' => $this->vehicleError($vin, $saleVehicle, $customerId),
        ]);

        if ($errors !== [] || !$saleVehicle instanceof SaleVehicle) {
            return new SaleCheck($errors, null);
        }

        return new SaleCheck([], new SaleDetails($saleVehicle->id, $customerId, $salespersonId, (int) $price, $soldOn));
    }

    /**
     * @param array<string, string> $errors Each field's message, by name; empty for a field that passed.
     *
     * @return array<string, string> The refused fields' messages only, in the form's order.
     */
    private function refused(array $errors): array
    {
        $refused = [];

        foreach (SaleEntry::FIELDS as $field) {
            $message = $errors[$field] ?? '';

            if ($message === '') {
                continue;
            }

            $refused[$field] = $message;
        }

        return $refused;
    }

    /**
     * @param string           $vin         The posted VIN, in capitals.
     * @param SaleVehicle|null $saleVehicle The vehicle with it; null when none has it.
     * @param int              $customerId  The customer the sale names; 0 for none.
     *
     * @throws QueryException           When the vehicle's hold cannot be read.
     * @throws InvalidArgumentException When its entity type is malformed.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function vehicleError(string $vin, ?SaleVehicle $saleVehicle, int $customerId): string
    {
        if ($vin === '') {
            return 'Choose the vehicle.';
        }

        if (!$saleVehicle instanceof SaleVehicle) {
            return 'Choose a vehicle on the lot.';
        }

        if ($saleVehicle->sold) {
            return self::ALREADY_SOLD;
        }

        return $this->holdRepository->heldForAnother($saleVehicle->id, $customerId)
            ? self::HELD
            : '';
    }

    /**
     * @param string $price The posted price, trimmed.
     *
     * @return string Why it is refused; empty when it is whole dollars from 1 to 9,999,999.
     */
    private function priceError(string $price): string
    {
        return preg_match('/^[1-9][0-9]{0,6}$/', $price) === 1
            ? ''
            : 'The sale price is whole dollars, from 1 to 9,999,999.';
    }

    /**
     * @param string $key  The posted key.
     * @param int    $id   The id of what has it; 0 when nothing has it.
     * @param string $what What it names, `customer` or `salesperson`.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function choiceError(string $key, int $id, string $what): string
    {
        if (trim($key) === '') {
            return 'Choose the ' . $what . '.';
        }

        return $id === 0
            ? 'Choose a ' . $what . ' on the list.'
            : '';
    }

    /**
     * Compares days as `YYYY-MM-DD` text with `strcmp()`: elephc refuses `<` and `>` between two strings (old Lotline's
     * ticket 28); no AIC ticket carries it yet.
     *
     * @param string           $soldOn      The posted day.
     * @param string           $today       The database's today.
     * @param SaleVehicle|null $saleVehicle The vehicle sold, when it is known.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function dayError(string $soldOn, string $today, ?SaleVehicle $saleVehicle): string
    {
        $real = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $soldOn) === 1
            && checkdate((int) substr($soldOn, 5, 2), (int) substr($soldOn, 8, 2), (int) substr($soldOn, 0, 4));

        if (!$real) {
            return 'The sale date is a real date, as YYYY-MM-DD.';
        }

        if (strcmp($soldOn, $today) > 0) {
            return 'The sale date cannot be after today.';
        }

        if ($saleVehicle instanceof SaleVehicle && strcmp($soldOn, $saleVehicle->addedOn) < 0) {
            return 'The sale date is before the vehicle came onto the lot, ' . $saleVehicle->addedOn . '.';
        }

        return '';
    }
}
