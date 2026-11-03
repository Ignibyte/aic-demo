<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\BulkAction;
use App\Inventory\Entity\BulkEdit;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleLot;
use App\Inventory\Entity\VehiclePrice;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Repository\VehicleRecordRepository;
use App\Inventory\Service\SoldVehicles;
use App\Inventory\Service\VehicleStatuses;
use App\Inventory\Service\Vins;
use InvalidArgumentException;
use UnexpectedValueException;

use function checkdate;
use function implode;
use function mb_strlen;
use function number_format;
use function preg_match;
use function strtoupper;
use function substr;
use function trim;

/**
 * The vehicle form's checks, one per field: the VIN's form, check digit and uniqueness, the model year's range, the
 * texts and their column lengths, whole numbers from 0, a list price no lower than the cost, a known status (sold, for a
 * vehicle with a recorded sale) and a real date. A valid entry becomes the `Vehicle` it describes. A list's row saves
 * through them too (#28), and a bulk edit of the list checks each of its vehicles here (#29), a status through the
 * same checks.
 */
final readonly class VehicleChecks
{
    /**
     * Why a VIN another vehicle holds is refused.
     */
    public const string VIN_TAKEN = 'Another vehicle on the lot has this VIN.';

    /**
     * Why a vehicle with a recorded sale keeps its status.
     */
    public const string SALE_RECORDED = 'This vehicle has a recorded sale. Void the sale to put it back on the lot.';

    /**
     * Why a bulk edit may not set a vehicle sold (#29): a sale does that.
     */
    public const string SOLD_BY_SALE = 'A sale marks a vehicle sold: record the sale on its page.';

    /**
     * Why a bulk edit does not move a sold vehicle.
     */
    public const string SOLD_GONE = 'It is sold and has left the lot.';

    /**
     * Why a bulk edit does not delete a sold vehicle, or one with a recorded sale.
     */
    public const string SOLD_KEPT = 'It is sold, so it stays on the record.';

    /**
     * Why a bulk edit refuses a VIN no vehicle has.
     */
    public const string NOT_LISTED = 'It is no longer on the lot.';

    /**
     * The first model year with a 17-character VIN.
     */
    private const int FIRST_YEAR = 1_981;

    /**
     * @param VehicleRecordRepository $vehicleRecordRepository Knows which VINs are taken.
     * @param SoldVehicles            $soldVehicles            Knows which vehicles have a recorded sale.
     * @param Vins                    $vins                    Works out check digits.
     * @param VehicleStatuses         $vehicleStatuses         The statuses.
     * @param VehicleEntries          $vehicleEntries          Fills a stored vehicle's entry, which a bulk status runs on.
     */
    public function __construct(
        private VehicleRecordRepository $vehicleRecordRepository,
        private SoldVehicles $soldVehicles,
        private Vins $vins = new Vins(),
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
        private VehicleEntries $vehicleEntries = new VehicleEntries(),
    ) {
    }

    /**
     * @param VehicleEntry $vehicleEntry What was posted.
     * @param string       $currentVin   The VIN of the vehicle being changed, which it may keep; empty for a new one.
     * @param string       $today        The database's today, `YYYY-MM-DD`; next year is the last model year.
     *
     * @throws QueryException           When the taken VINs or the vehicle's sale cannot be read.
     * @throws InvalidArgumentException When an entity type it goes through is malformed.
     *
     * @return array<string, string> Why each refused field is refused, by name; empty when the entry is valid.
     */
    public function errors(VehicleEntry $vehicleEntry, string $currentVin, string $today): array
    {
        $errors = [
            'added_on' => $this->dateError(trim($vehicleEntry->value('added_on'))),
            'body_style' => $this->textError($vehicleEntry->value('body_style'), 'body style', 30, true),
            'cost' => $this->wholeError($vehicleEntry->value('cost'), 'The cost is whole dollars, 0 or more.'),
            'exterior_colour' => $this->textError($vehicleEntry->value('exterior_colour'), 'colour', 30, true),
            'list_price' => $this->priceError($vehicleEntry->value('list_price'), $vehicleEntry->value('cost')),
            'make' => $this->textError($vehicleEntry->value('make'), 'make', 40, true),
            'mileage' => $this->wholeError($vehicleEntry->value('mileage'), 'Mileage is a whole number of miles, 0 or more.'),
            'model' => $this->textError($vehicleEntry->value('model'), 'model', 60, true),
            'status' => $this->statusError($vehicleEntry->value('status'), $currentVin),
            'trim' => $this->textError($vehicleEntry->value('trim'), 'trim', 60, false),
            'vin' => $this->vinError($this->vin($vehicleEntry), $currentVin),
            'year' => $this->yearError(trim($vehicleEntry->value('year')), (int) substr($today, 0, 4) + 1),
        ];

        return $this->refused($errors);
    }

    /**
     * @param VehicleEntry $vehicleEntry An entry `errors()` found valid.
     *
     * @throws UnexpectedValueException When its status is no status.
     *
     * @return Vehicle The vehicle it describes: the VIN in capitals, the texts trimmed, and the entry's location (#29),
     *                 which only a stored vehicle's entry carries and no save writes.
     */
    public function vehicle(VehicleEntry $vehicleEntry): Vehicle
    {
        $vehiclePrice = new VehiclePrice((int) trim($vehicleEntry->value('list_price')), (int) trim($vehicleEntry->value('cost')));

        return new Vehicle(
            $this->vin($vehicleEntry),
            (int) trim($vehicleEntry->value('year')),
            trim($vehicleEntry->value('make')),
            trim($vehicleEntry->value('model')),
            trim($vehicleEntry->value('trim')),
            trim($vehicleEntry->value('body_style')),
            (int) trim($vehicleEntry->value('mileage')),
            trim($vehicleEntry->value('exterior_colour')),
            $vehiclePrice,
            $this->vehicleStatuses->stored($vehicleEntry->value('status')),
            new VehicleLot(trim($vehicleEntry->value('added_on')), $this->vehicleStatuses->location($vehicleEntry->value('location'))),
        );
    }

    /**
     * @param BulkEdit $bulkEdit A bulk edit of the list that can be applied as posted (#29).
     * @param Vehicle  $vehicle  One of its vehicles, read for update.
     * @param string   $today    The database's today, `YYYY-MM-DD`.
     *
     * @throws QueryException           When the taken VINs or the vehicle's sale cannot be read.
     * @throws InvalidArgumentException When an entity type it goes through is malformed.
     *
     * @return string Why the edit is refused for the vehicle; empty when it may take it. A status runs this form's own
     *                checks over the stored vehicle with the status put in, and sold is a sale's to give; a sold vehicle
     *                is not moved, and a sold one or one with a recorded sale is not deleted.
     */
    public function bulkError(BulkEdit $bulkEdit, Vehicle $vehicle, string $today): string
    {
        if ($bulkEdit->bulkAction === BulkAction::Status) {
            return $this->bulkStatusError($bulkEdit->vehicleStatus, $vehicle, $today);
        }

        $kept = $vehicle->status === VehicleStatus::Sold
            || ($bulkEdit->deletes() && $this->soldVehicles->sale($vehicle->vin) !== null);

        if (!$kept) {
            return '';
        }

        return $bulkEdit->deletes()
            ? self::SOLD_KEPT
            : self::SOLD_GONE;
    }

    /**
     * @param ?VehicleStatus $vehicleStatus The status a bulk edit gives.
     * @param Vehicle        $vehicle       One of its vehicles, as stored.
     * @param string         $today         The database's today, `YYYY-MM-DD`.
     *
     * @throws QueryException           When the taken VINs or the vehicle's sale cannot be read.
     * @throws InvalidArgumentException When an entity type it goes through is malformed.
     *
     * @return string Why the vehicle may not take the status; empty when it may.
     */
    private function bulkStatusError(?VehicleStatus $vehicleStatus, Vehicle $vehicle, string $today): string
    {
        if (!$vehicleStatus instanceof VehicleStatus || $vehicleStatus === VehicleStatus::Sold) {
            return self::SOLD_BY_SALE;
        }

        $values = $this->vehicleEntries->filled($vehicle)->values;
        $values['status'] = $vehicleStatus->value;

        return implode(' ', $this->errors(new VehicleEntry($values), $vehicle->vin, $today));
    }

    /**
     * @param array<string, string> $errors Each field's message, by name; empty for a field that passed.
     *
     * @return array<string, string> The refused fields' messages only.
     */
    private function refused(array $errors): array
    {
        $refused = [];

        foreach ($errors as $name => $error) {
            if ($error === '') {
                continue;
            }

            $refused[$name] = $error;
        }

        return $refused;
    }

    /**
     * @param VehicleEntry $vehicleEntry What was posted.
     *
     * @return string Its VIN, trimmed and in capitals.
     */
    private function vin(VehicleEntry $vehicleEntry): string
    {
        return strtoupper(trim($vehicleEntry->value('vin')));
    }

    /**
     * @param string $vin        A posted VIN, in capitals.
     * @param string $currentVin The VIN of the vehicle being changed; empty for a new one.
     *
     * @throws QueryException           When the taken VINs cannot be read.
     * @throws InvalidArgumentException When an entity type it goes through is malformed.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function vinError(string $vin, string $currentVin): string
    {
        if ($vin === '') {
            return 'Enter the VIN.';
        }

        if (preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin) !== 1) {
            return 'A VIN is 17 letters and digits, without I, O or Q.';
        }

        $checkDigit = $this->vins->checkDigit($vin);

        if (substr($vin, 8, 1) !== $checkDigit) {
            return 'This VIN does not add up: its ninth character, the check digit, would be ' . $checkDigit . '.';
        }

        return $this->vehicleRecordRepository->taken($vin, $currentVin)
            ? self::VIN_TAKEN
            : '';
    }

    /**
     * @param string $year       A posted year, trimmed.
     * @param int    $latestYear The newest model year: next year.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function yearError(string $year, int $latestYear): string
    {
        if (preg_match('/^\\d{4}$/', $year) === 1 && (int) $year >= self::FIRST_YEAR && (int) $year <= $latestYear) {
            return '';
        }

        return 'The year is a model year from ' . self::FIRST_YEAR . ' to ' . $latestYear . '.';
    }

    /**
     * @param string $text     A posted text.
     * @param string $label    What it is, as `the …` reads it.
     * @param int    $longest  Its column's length.
     * @param bool   $required Whether it must be given.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function textError(string $text, string $label, int $longest, bool $required): string
    {
        $trimmed = trim($text);

        if ($required && $trimmed === '') {
            return 'Enter the ' . $label . '.';
        }

        return mb_strlen($trimmed) > $longest
            ? 'The ' . $label . ' is at most ' . $longest . ' characters.'
            : '';
    }

    /**
     * @param string $value   A posted number.
     * @param string $message Why a value that is not a whole number from 0 is refused.
     *
     * @return string The message when it is refused; empty when it is 1 to 7 digits.
     */
    private function wholeError(string $value, string $message): string
    {
        return preg_match('/^\\d{1,7}$/', trim($value)) === 1
            ? ''
            : $message;
    }

    /**
     * @param string $listPrice A posted list price.
     * @param string $cost      A posted cost.
     *
     * @return string Why the list price is refused: not whole dollars, or below a cost that is (#28); empty when it is
     *                fine.
     */
    private function priceError(string $listPrice, string $cost): string
    {
        $error = $this->wholeError($listPrice, 'The list price is whole dollars, 0 or more.');
        $below = $error === '' && preg_match('/^\\d{1,7}$/', trim($cost)) === 1 && (int) trim($listPrice) < (int) trim($cost);

        return $below
            ? 'The list price is below the cost, $' . number_format((int) trim($cost)) . '.'
            : $error;
    }

    /**
     * @param string $status     A posted status.
     * @param string $currentVin The VIN of the vehicle being changed; empty for a new one.
     *
     * @throws QueryException When the vehicle's sale cannot be read.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function statusError(string $status, string $currentVin): string
    {
        foreach ($this->vehicleStatuses->all() as $vehicleStatus) {
            if ($vehicleStatus->value === $status) {
                return $this->saleError($status, $currentVin);
            }
        }

        return 'Choose a status.';
    }

    /**
     * @param string $status     A known status.
     * @param string $currentVin The VIN of the vehicle being changed; empty for a new one.
     *
     * @throws QueryException When the vehicle's sale cannot be read.
     *
     * @return string Why a status but sold is refused for a vehicle with a recorded sale; empty otherwise.
     */
    private function saleError(string $status, string $currentVin): string
    {
        if ($currentVin === '' || $status === VehicleStatus::Sold->value) {
            return '';
        }

        return $this->soldVehicles->sale($currentVin) !== null
            ? self::SALE_RECORDED
            : '';
    }

    /**
     * @param string $date A posted date, trimmed.
     *
     * @return string Why it is refused; empty when it is a real day as `YYYY-MM-DD`.
     */
    private function dateError(string $date): string
    {
        $real = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) === 1
            && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));

        return $real
            ? ''
            : 'The day it came onto the lot is a real date, as YYYY-MM-DD.';
    }
}
