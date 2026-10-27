<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePrice;
use App\Inventory\Repository\VehicleRecordRepository;
use App\Inventory\Service\VehicleStatuses;
use App\Inventory\Service\Vins;
use UnexpectedValueException;

use function checkdate;
use function mb_strlen;
use function preg_match;
use function strtoupper;
use function substr;
use function trim;

/**
 * The vehicle form's checks, one per field: the VIN's form, check digit and uniqueness, the model year's range, the
 * texts and their column lengths, whole numbers from 0, a known status and a real date. A valid entry becomes the
 * `Vehicle` it describes.
 */
final readonly class VehicleChecks
{
    /**
     * Why a VIN another vehicle holds is refused.
     */
    public const string VIN_TAKEN = 'Another vehicle on the lot has this VIN.';

    /**
     * The first model year with a 17-character VIN.
     */
    private const int FIRST_YEAR = 1_981;

    /**
     * @param VehicleRecordRepository $vehicleRecordRepository Knows which VINs are taken.
     * @param Vins                    $vins                    Works out check digits.
     * @param VehicleStatuses         $vehicleStatuses         The statuses.
     */
    public function __construct(
        private VehicleRecordRepository $vehicleRecordRepository,
        private Vins $vins = new Vins(),
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
    ) {
    }

    /**
     * @param VehicleEntry $vehicleEntry What was posted.
     * @param string       $currentVin   The VIN of the vehicle being changed, which it may keep; empty for a new one.
     * @param string       $today        The database's today, `YYYY-MM-DD`; next year is the last model year.
     *
     * @throws QueryException When the taken VINs cannot be read.
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
            'list_price' => $this->wholeError($vehicleEntry->value('list_price'), 'The list price is whole dollars, 0 or more.'),
            'make' => $this->textError($vehicleEntry->value('make'), 'make', 40, true),
            'mileage' => $this->wholeError($vehicleEntry->value('mileage'), 'Mileage is a whole number of miles, 0 or more.'),
            'model' => $this->textError($vehicleEntry->value('model'), 'model', 60, true),
            'status' => $this->statusError($vehicleEntry->value('status')),
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
     * @return Vehicle The vehicle it describes: the VIN in capitals, the texts trimmed.
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
            trim($vehicleEntry->value('added_on')),
        );
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
     * @throws QueryException When the taken VINs cannot be read.
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
     * @param string $status A posted status.
     *
     * @return string Why it is refused; empty when it is a status's stored value.
     */
    private function statusError(string $status): string
    {
        foreach ($this->vehicleStatuses->all() as $vehicleStatus) {
            if ($vehicleStatus->value === $status) {
                return '';
            }
        }

        return 'Choose a status.';
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
