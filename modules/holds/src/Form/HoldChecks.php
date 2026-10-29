<?php

declare(strict_types=1);

namespace App\Holds\Form;

use AIC\Database\Contracts\QueryException;
use App\Holds\Entity\HoldDetails;
use App\Holds\Repository\HoldChoiceRepository;
use App\Inventory\Entity\VehicleStatus;
use InvalidArgumentException;

use function strlen;
use function strtoupper;
use function trim;

/**
 * The hold form's checks, field by field (#20): a vehicle in stock, a customer, and a note of 1 to 1,000 bytes (counted as bytes, as
 * leads counts a message);
 * and the hold a valid entry describes, placed by the member of staff signed in.
 */
final readonly class HoldChecks
{
    /**
     * Why a vehicle that is held or sold is refused.
     */
    public const string NOT_FOR_SALE = 'This vehicle is not for sale now.';

    /**
     * The longest note, in bytes.
     */
    private const int NOTE_BYTES = 1_000;

    /**
     * @param HoldChoiceRepository $holdChoiceRepository Finds what the entry names.
     */
    public function __construct(
        private HoldChoiceRepository $holdChoiceRepository,
    ) {
    }

    /**
     * @param HoldEntry $holdEntry What was posted.
     * @param string    $login     The signed-in member's login.
     *
     * @throws QueryException           When what the entry names cannot be read.
     * @throws InvalidArgumentException When an entity type it goes through is malformed.
     *
     * @return HoldCheck Each refused field's message, or the hold to place.
     */
    public function check(HoldEntry $holdEntry, string $login): HoldCheck
    {
        $vin = strtoupper(trim($holdEntry->value('vehicle')));
        $customer = trim($holdEntry->value('customer'));
        $customerId = $this->holdChoiceRepository->customerId($customer);
        $note = trim($holdEntry->value('note'));
        $errors = [];
        $vehicleError = $this->vehicleError($vin);
        $customerError = $this->customerError($customer, $customerId);
        $noteError = $this->noteError($note);

        foreach (['vehicle' => $vehicleError, 'customer' => $customerError, 'note' => $noteError] as $field => $message) {
            if ($message === '') {
                continue;
            }

            $errors[$field] = $message;
        }

        if ($errors !== []) {
            return new HoldCheck($errors, null);
        }

        $vehicleId = $this->holdChoiceRepository->vehicleId($vin);
        $placedBy = $this->holdChoiceRepository->memberId($login);

        return new HoldCheck([], new HoldDetails($vehicleId, $customerId, $placedBy, $note));
    }

    /**
     * @param string $vin The posted VIN, in capitals.
     *
     * @throws QueryException           When the vehicle cannot be read.
     * @throws InvalidArgumentException When its entity type is malformed.
     *
     * @return string Why it is refused; empty when it is in stock.
     */
    private function vehicleError(string $vin): string
    {
        if ($vin === '') {
            return 'Choose the vehicle.';
        }

        $status = $this->holdChoiceRepository->vehicleStatus($vin);

        if ($status === '') {
            return 'Choose a vehicle on the lot.';
        }

        return $status === VehicleStatus::InStock->value
            ? ''
            : self::NOT_FOR_SALE;
    }

    /**
     * @param string $customer   The posted email, trimmed.
     * @param int    $customerId The id of the customer who has it; 0 for none.
     *
     * @return string Why it is refused; empty when it is a customer's.
     */
    private function customerError(string $customer, int $customerId): string
    {
        if ($customer === '') {
            return 'Choose the customer.';
        }

        return $customerId === 0
            ? 'Choose a customer on the list.'
            : '';
    }

    /**
     * @param string $note The posted note, trimmed.
     *
     * @return string Why it is refused; empty when it is 1 to 1,000 bytes.
     */
    private function noteError(string $note): string
    {
        if ($note === '') {
            return 'Say why the vehicle is held, such as when the customer will come.';
        }

        return strlen($note) > self::NOTE_BYTES
            ? 'Keep the note short: a few lines at most.'
            : '';
    }
}
