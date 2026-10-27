<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use function strpos;
use function substr;

/**
 * Invented VINs that no vehicle carries: each starts with the world manufacturer identifier `0LT`, and no region is
 * assigned a leading `0`, yet each is a well-formed VIN, with the model-year code at position 10 and the ISO 3779
 * check digit at position 9.
 */
final readonly class Vins
{
    /**
     * The world manufacturer identifier, unassigned.
     */
    private const string MAKER = '0LT';

    /**
     * The letters a VIN may hold (no I, O or Q).
     */
    private const string LETTERS = 'ABCDEFGHJKLMNPRSTUVWXYZ';

    /**
     * The model-year codes, from 2010.
     */
    private const string YEARS = 'ABCDEFGHJKLMNPRSTVWXY';

    /**
     * Every character, and below it in VALUES its value for the check digit.
     */
    private const string CHARACTERS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Each character's value, by its place in CHARACTERS; I, O and Q, which a VIN never holds, are 0.
     */
    private const string VALUES = '012345678912345678012345070923456789';

    /**
     * Each position's weight; the check digit's own position weighs 0.
     */
    private const array WEIGHTS = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * @param int $make  The make's place in the seed, 0 to 22.
     * @param int $model The model's place in its make, 0 to 22.
     * @param int $trim  The trim's place in its model, 0 to 22.
     * @param int $index The vehicle's place in the seed, 0 to 99.
     * @param int $year  Its model year, 2010 to 2030.
     *
     * @return string Its VIN: the maker, a descriptor of the make, model, trim and place, the check digit, the
     *                model-year code, `R` for the plant and a six-digit serial.
     */
    public function vin(int $make, int $model, int $trim, int $index, int $year): string
    {
        $descriptor = $this->letter($make) . $this->letter($model) . $this->letter($trim) . ($index < 10 ? '0' : '') . $index;
        $serial = (string) (104_729 + $index * 7_919);

        return $this->withCheckDigit(self::MAKER . $descriptor . '0' . substr(self::YEARS, $year - 2_010, 1) . 'R' . $serial);
    }

    /**
     * @param int $place A place, 0 to 22.
     *
     * @return string The VIN letter at that place.
     */
    private function letter(int $place): string
    {
        return substr(self::LETTERS, $place, 1);
    }

    /**
     * @param string $vin A 17-character VIN whose position 9 is a placeholder.
     *
     * @return string The VIN with its check digit there: the sum of each character's value times its position's
     *                weight, modulo 11, with 10 written `X`.
     */
    private function withCheckDigit(string $vin): string
    {
        $sum = 0;

        foreach (self::WEIGHTS as $position => $weight) {
            $sum += $this->value(substr($vin, $position, 1)) * $weight;
        }

        $remainder = $sum % 11;

        return substr($vin, 0, 8) . ($remainder === 10 ? 'X' : (string) $remainder) . substr($vin, 9);
    }

    /**
     * @param string $character A VIN character.
     *
     * @return int Its value for the check digit.
     */
    private function value(string $character): int
    {
        $place = strpos(self::CHARACTERS, $character);

        return $place === false
            ? 0
            : (int) substr(self::VALUES, $place, 1);
    }
}
